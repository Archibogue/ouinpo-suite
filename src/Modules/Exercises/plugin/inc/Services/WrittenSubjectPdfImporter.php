<?php

namespace Ouinpo\Exercises\Services;

use Ouinpo\Suite\Core\AiSettings;

defined('ABSPATH') || exit;

final class WrittenSubjectPdfImporter
{
    private const MAX_PDF_TEXT_CHARS = 90000;
    private const MAX_SOURCE_TRANSCRIPT_CHARS = 120000;

    public function import(array $request): array|\WP_Error
    {
        if (!AiSettings::enabled_for_usage('pedagogical_suggestions')) {
            return new \WP_Error('ai_disabled', 'Usage IA desactive dans les reglages.');
        }

        if (!class_exists('\OuInPo\SegFault\OpenAI') || !method_exists('\OuInPo\SegFault\OpenAI', 'respond')) {
            return new \WP_Error('ai_unavailable', 'Moteur IA indisponible.');
        }

        $quota = AiSettings::consumeUserRateLimit(
            'teacher_ai',
            (int) get_current_user_id(),
            AiSettings::quota('ouinpo_ai_teacher_per_minute'),
            AiSettings::quota('ouinpo_ai_teacher_per_day')
        );

        if (is_wp_error($quota)) {
            return $quota;
        }

        $file_path = (string) ($request['file_path'] ?? '');
        if ($file_path === '' || !is_readable($file_path)) {
            return new \WP_Error('missing_pdf', 'PDF introuvable ou illisible.');
        }

        $file_url = esc_url_raw((string) ($request['file_url'] ?? ''));
        $text = self::extract_pdf_text($file_path);
        $text_source = 'local_pdf';
        if (mb_strlen(trim($text)) < 500 && $file_url !== '' && (string) AiSettings::get('ouinpo_ai_ocr_provider') === 'albert') {
            $ocr_url = \Ouinpo\Exercises\WrittenFiles::signed_download_url_for_upload_url($file_url);
            if ($ocr_url === '') {
                return new \WP_Error('ocr_signed_url_unavailable', 'Impossible de generer une URL temporaire securisee pour l OCR. OCR distant annule.');
            }

            $ocr_text = self::extract_pdf_text_with_albert_ocr($ocr_url);
            if (mb_strlen(trim($ocr_text)) > mb_strlen(trim($text))) {
                $text = $ocr_text;
                $text_source = 'albert_ocr';
            }
        }

        AiSettings::debug_log('Written PDF text extracted', [
            'stage' => 'written_subject_pdf_import',
            'source' => $text_source,
            'text_length' => mb_strlen(trim($text)),
        ]);

        if (mb_strlen(trim($text)) < 500) {
            return new \WP_Error('empty_pdf_text', 'Le texte extrait du PDF est trop court. Si le sujet est scanne, verifie la configuration OCR Albert et que le fichier stocke est accessible par URL.');
        }

        $context = $this->build_context($request, $text);
        if (is_wp_error($context)) {
            return $context;
        }

        $answer = \OuInPo\SegFault\OpenAI::respond($this->messages($context), [
            'temperature' => 0.15,
            'max_tokens' => 16000,
            'response_format' => ['type' => 'json_object'],
            'albert_purpose' => 'chat',
        ]);

        AiSettings::debug_log('Written subject PDF AI response received', [
            'stage' => 'written_subject_pdf_import',
            'provider' => (string) AiSettings::get('ouinpo_ai_logged_provider'),
            'raw_length' => strlen($answer),
        ]);

        $json = AiJsonResponseParser::parse($answer, 'object');
        if (is_wp_error($json)) {
            return $json;
        }

        $validated = $this->validate($json, $context);
        if (is_wp_error($validated)) {
            return $validated;
        }

        $subject_id = $this->persist($validated, $context);
        if (is_wp_error($subject_id)) {
            return $subject_id;
        }

        return [
            'subject_id' => (int) $subject_id,
            'slug' => (string) $validated['slug'],
            'title' => (string) $validated['title'],
            'subject_group' => (string) $validated['subject_group'],
        ];
    }

    public static function extract_pdf_text(string $file_path): string
    {
        if (class_exists('\OuInPo\SegFault\RAG') && method_exists('\OuInPo\SegFault\RAG', 'pdf_to_text')) {
            $text = \OuInPo\SegFault\RAG::pdf_to_text($file_path);
            if (is_string($text) && trim($text) !== '') {
                return self::normalize_text($text);
            }
        }

        if (!class_exists('\Smalot\PdfParser\Parser')) {
            $autoload = defined('OUINPO_SUITE_DIR')
                ? trailingslashit(OUINPO_SUITE_DIR) . 'src/Modules/SegFault/plugin/libs/pdfparser/alt_autoload.php-dist'
                : dirname(__DIR__, 4) . '/SegFault/plugin/libs/pdfparser/alt_autoload.php-dist';

            if (is_file($autoload)) {
                require_once $autoload;
            }
        }

        if (class_exists('\Smalot\PdfParser\Parser')) {
            try {
                $parser = new \Smalot\PdfParser\Parser();
                $pdf = $parser->parseFile($file_path);
                $text = $pdf->getText();
                if (is_string($text) && trim($text) !== '') {
                    return self::normalize_text($text);
                }
            } catch (\Throwable $e) {
                AiSettings::debug_log('Written PDF parser failed', ['error' => $e->getMessage()]);
            }
        }

        return '';
    }

    public static function extract_pdf_text_with_albert_ocr(string $file_url): string
    {
        $file_url = esc_url_raw(trim($file_url));
        if ($file_url === '' || !class_exists('\OuInPo\SegFault\Albert') || !method_exists('\OuInPo\SegFault\Albert', 'ocr_document')) {
            return '';
        }

        $payload = \OuInPo\SegFault\Albert::ocr_document($file_url, [
            'include_image_base64' => false,
        ]);

        if (is_wp_error($payload)) {
            AiSettings::debug_log('Written PDF OCR failed', ['error' => $payload->get_error_message()]);
            return '';
        }

        $text = self::collect_ocr_text($payload);
        return self::normalize_text($text);
    }

    private static function collect_ocr_text($payload): string
    {
        $chunks = [];
        self::collect_ocr_text_chunks($payload, '', $chunks);

        return implode("\n\n", array_values(array_filter(array_map('trim', $chunks))));
    }

    private static function collect_ocr_text_chunks($value, string $key, array &$chunks): void
    {
        if (is_string($value)) {
            if (in_array($key, ['markdown', 'text', 'content', 'html'], true) && mb_strlen(trim($value)) > 20) {
                $chunks[] = $value;
            }
            return;
        }

        if (!is_array($value)) {
            return;
        }

        foreach ($value as $child_key => $child_value) {
            self::collect_ocr_text_chunks($child_value, is_string($child_key) ? $child_key : '', $chunks);
        }
    }

    private static function normalize_text(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        return trim($text);
    }

    private function build_context(array $request, string $pdf_text): array|\WP_Error
    {
        global $wpdb;

        $p = $wpdb->prefix . 'ouin_exo_';
        $level_ids = array_values(array_unique(array_filter(array_map('intval', (array) ($request['school_levels'] ?? [])))));
        $source_type = sanitize_key((string) ($request['source_type'] ?? 'annale'));
        if (!in_array($source_type, ['annale', 'inspired', 'type_bac'], true)) {
            $source_type = 'annale';
        }

        $level_where = '';
        $args = [];
        if ($level_ids) {
            $placeholders = implode(',', array_fill(0, count($level_ids), '%d'));
            $level_where = "INNER JOIN {$p}competency_school_level csl ON csl.competency_id = c.id AND csl.school_level_id IN ({$placeholders})";
            $args = $level_ids;
        }

        $sql = "
            SELECT DISTINCT c.id, c.domain, c.domain_slug, c.competency, c.label, c.level, c.track
            FROM {$p}competencies c
            {$level_where}
            WHERE c.active = 1
            ORDER BY c.track, c.level, c.domain, c.id
        ";

        $competencies = $args
            ? $wpdb->get_results($wpdb->prepare($sql, $args), ARRAY_A)
            : $wpdb->get_results($sql, ARRAY_A);

        if (!$competencies) {
            return new \WP_Error('missing_competencies', 'Aucune competence BO disponible pour structurer le sujet.');
        }

        return [
            'pdf_text' => mb_substr($pdf_text, 0, self::MAX_PDF_TEXT_CHARS),
            'source_text' => mb_substr($pdf_text, 0, self::MAX_SOURCE_TRANSCRIPT_CHARS),
            'competencies' => $competencies,
            'competency_ids' => array_map(static fn(array $row): int => (int) $row['id'], $competencies),
            'school_levels' => $level_ids,
            'source_type' => $source_type,
            'fallback_title' => sanitize_text_field((string) ($request['fallback_title'] ?? 'Annale NSI')),
            'source_filename' => sanitize_file_name((string) ($request['source_filename'] ?? '')),
            'existing_subject_id' => max(0, (int) ($request['existing_subject_id'] ?? 0)),
        ];
    }

    private function messages(array $context): array
    {
        $competency_lines = array_map(static function (array $c): string {
            $label = trim((string) (($c['label'] ?? '') ?: ($c['competency'] ?? '')));
            return '- ID ' . (int) $c['id'] . ' : ' . trim((string) ($c['track'] ?? '') . ' ' . (string) ($c['level'] ?? '') . ' - ' . (string) ($c['domain'] ?? '') . ' - ' . $label);
        }, $context['competencies']);

        $schema = [
            'title' => 'Centres etrangers 2025 sujet 1',
            'slug' => 'centres-etrangers-2025-sujet-1',
            'session_label' => 'Centres etrangers sujet 1',
            'year_label' => '2025',
            'center_label' => 'Centres etrangers',
            'subject_group' => 'centres-etrangers-2025-sujet-1',
            'estimated_minutes' => 210,
            'statement_html' => '<p>Presentation generale du sujet, consignes globales, duree, contexte commun a tout le sujet.</p>',
            'exercises' => [[
                'exercise_order' => 1,
                'title' => 'Exercice 1',
                'intro_html' => '<p>Contexte complet de l exercice : texte, definitions, tableaux, graphes decrits, classes, fonctions, signatures et blocs de code communs aux questions.</p><pre><code>def fonction_a_completer(...):\n    ...</code></pre>',
                'max_points' => null,
                'questions' => [[
                    'question_order' => 1,
                    'question_label' => '1.a',
                    'prompt_html' => '<p>Enonce exact de la sous-question, avec tout extrait specifique necessaire pour y repondre sans ouvrir le PDF.</p>',
                    'answer_type' => 'text',
                    'max_points' => null,
                    'competency_ids' => [123],
                    'hints' => [
                        ['rank' => 1, 'title' => 'Aide IA 1', 'html' => '<p>...</p>'],
                    ],
                ]],
            ]],
        ];

        $system = "Tu transformes un sujet officiel de bac NSI ecrit en structure JSON pour une plateforme pedagogique. Reponds uniquement avec un objet JSON valide, sans Markdown. Respecte le texte du sujet : ne cree pas de nouvelles questions. Decoupe les exercices et sous-questions, conserve tout le contexte, les consignes, tableaux, donnees, schemas decrits et extraits de code en HTML. Les aides IA doivent guider sans donner la solution complete. Pour les competences, utilise uniquement les IDs fournis et jamais 0.";

        $user = "Competences BO disponibles :\n" . implode("\n", $competency_lines) . "\n\n"
            . "Format JSON attendu :\n" . wp_json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n"
            . "Contraintes :\n"
            . "- Utilise exactement les cles JSON de l'exemple : exercises, questions, prompt_html, competency_ids, hints, html.\n"
            . "- Une entree exercises par exercice du sujet officiel.\n"
            . "- Le nombre d entrees exercises doit correspondre a tous les titres Exercice 1, Exercice 2, Exercice 3, etc. presents dans le texte. Ne t arrete pas apres le premier exercice.\n"
            . "- Renseigne obligatoirement session_label, year_label et center_label a partir de l en-tete du sujet ou, si besoin, du nom de fichier et du titre de secours.\n"
            . "- Une entree questions par sous-question reelle, par exemple 1.a, 1.b, 2.\n"
            . "- L eleve doit pouvoir faire le sujet sans ouvrir le PDF : aucun contexte utile ne doit manquer.\n"
            . "- Place dans intro_html tout le contexte commun de l exercice : texte introductif, definitions, donnees, tableaux, schemas decrits, classes, fonctions, signatures et blocs de code utilises par plusieurs questions. Ne le duplique pas dans chaque sous-question.\n"
            . "- Place dans prompt_html l enonce exact de la sous-question et les extraits propres a cette question. Si la question demande de completer une fonction, affiche la fonction complete a completer avec sa signature et son squelette dans intro_html si elle sert a plusieurs questions, sinon dans prompt_html.\n"
            . "- Ne remplace jamais un bloc de code, une table ou des donnees par des points de suspension. Recopie les blocs necessaires integralement, sauf si le PDF est illisible.\n"
            . "- Utilise <pre><code>...</code></pre> pour les programmes, requetes SQL, arbres/graphes textuels et sorties console ; utilise des tableaux HTML pour les donnees tabulaires.\n"
            . "- Chaque question doit avoir au moins une competence BO pertinente ; si tu hesites, choisis l'ID le plus proche dans la liste fournie.\n"
            . "- N'utilise une competence transversale comme autonomie, projet, oral ou documentation que si la question porte explicitement sur la methode de travail ou la communication. Pour une question technique, par exemple conversion decimal/binaire, choisis une competence de contenu technique.\n"
            . "- Chaque question doit avoir 1 a 3 aides progressives.\n"
            . "- answer_type vaut text, code, sql ou mixed.\n"
            . "- Si une partie du PDF est illisible, signale-le dans statement_html, mais ne l'invente pas.\n\n"
            . "Titre de secours : " . (string) $context['fallback_title'] . "\n"
            . "Nom du fichier source : " . (string) ($context['source_filename'] ?? '') . "\n\n"
            . "Texte extrait du PDF :\n" . (string) $context['pdf_text'];

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $user],
        ];
    }

    private function validate(array $data, array $context): array|\WP_Error
    {
        $data = $this->normalize_payload($data);

        $title = sanitize_text_field((string) (self::first_value($data, ['title', 'titre', 'subject_title', 'nom']) ?? $context['fallback_title']));
        if ($title === '') {
            return new \WP_Error('invalid_title', 'Titre d annale manquant.');
        }

        $allowed_competencies = array_flip(array_map('intval', $context['competency_ids']));
        $exercises = [];

        foreach ((array) ($data['exercises'] ?? []) as $exercise_index => $exercise) {
            if (!is_array($exercise)) {
                continue;
            }

            $intro_html = ExerciseInsertService::clean_html((string) (self::first_value($exercise, ['intro_html', 'intro', 'statement_html', 'enonce_html', 'enonce', 'description']) ?? ''));
            $questions = [];
            foreach ((array) ($exercise['questions'] ?? []) as $question_index => $question) {
                if (!is_array($question)) {
                    continue;
                }

                $prompt = ExerciseInsertService::clean_html((string) (self::first_value($question, [
                    'prompt_html',
                    'prompt',
                    'statement_html',
                    'enonce_html',
                    'enonce',
                    'question_html',
                    'question_text',
                    'texte',
                    'text',
                    'content',
                ]) ?? ''));
                $label = sanitize_text_field((string) (self::first_value($question, [
                    'question_label',
                    'label',
                    'numero',
                    'number',
                    'id',
                    'reference',
                ]) ?? ''));
                if ($label === '') {
                    $label = (string) ($question_index + 1);
                }
                if (trim($prompt) === '') {
                    continue;
                }

                $competency_ids = [];
                foreach ($this->extract_competency_ids($question) as $cid) {
                    $cid = (int) $cid;
                    if ($cid > 0 && isset($allowed_competencies[$cid])) {
                        $competency_ids[] = $cid;
                    }
                }
                $competency_ids = array_values(array_unique($competency_ids));
                if (!$competency_ids) {
                    $competency_ids = $this->infer_competency_ids($prompt . ' ' . $intro_html, $context);
                }

                $hints = [];
                foreach ((array) (self::first_value($question, ['hints', 'aides', 'indices', 'help']) ?? []) as $hint_index => $hint) {
                    if (!is_array($hint)) {
                        $hint = ['html' => (string) $hint];
                    }
                    $html = ExerciseInsertService::clean_html((string) (self::first_value($hint, ['html', 'content', 'texte', 'text', 'aide']) ?? ''));
                    if (trim($html) === '') {
                        continue;
                    }
                    $hints[] = [
                        'rank' => max(1, (int) ($hint['rank'] ?? ($hint_index + 1))),
                        'title' => sanitize_text_field((string) ($hint['title'] ?? 'Aide IA')),
                        'html' => $html,
                    ];
                }
                if (!$hints) {
                    $hints[] = [
                        'rank' => 1,
                        'title' => 'Aide IA',
                        'html' => '<p>Repere les mots-cles de la question, puis relie-les a la notion de cours et aux donnees fournies avant de rediger.</p>',
                    ];
                }

                $answer_type = sanitize_key((string) ($question['answer_type'] ?? 'text'));
                if (!in_array($answer_type, ['text', 'code', 'sql', 'mixed'], true)) {
                    $answer_type = 'text';
                }

                $questions[] = [
                    'question_order' => max(1, (int) ($question['question_order'] ?? ($question_index + 1))),
                    'question_label' => $label,
                    'prompt_html' => $prompt,
                    'answer_type' => $answer_type,
                    'max_points' => $this->float_or_null(self::first_value($question, ['max_points', 'points', 'bareme'])),
                    'competency_ids' => $competency_ids,
                    'hints' => $hints,
                ];
            }

            if (!$questions) {
                continue;
            }

            $exercises[] = [
                'exercise_order' => max(1, (int) ($exercise['exercise_order'] ?? ($exercise_index + 1))),
                'title' => sanitize_text_field((string) (self::first_value($exercise, ['title', 'titre', 'label']) ?? 'Exercice ' . ($exercise_index + 1))),
                'intro_html' => $intro_html,
                'max_points' => $this->float_or_null(self::first_value($exercise, ['max_points', 'points', 'bareme'])),
                'questions' => $questions,
            ];
        }

        $exercises = $this->complete_missing_source_exercises($exercises, $context);

        if (!$exercises) {
            return new \WP_Error('invalid_structure', 'L IA n a pas produit d exercices exploitables.');
        }

        $exercises = $this->normalize_ordering($exercises);

        $slug = sanitize_title((string) (self::first_value($data, ['slug', 'permalink', 'identifiant']) ?? $title));
        if ($slug === '') {
            $slug = sanitize_title($title);
        }
        $existing_subject_id = max(0, (int) ($context['existing_subject_id'] ?? 0));
        $slug = $this->unique_slug($slug, $existing_subject_id);

        $subject_group = sanitize_text_field((string) (self::first_value($data, ['subject_group', 'group', 'groupe', 'serie']) ?? $slug));
        if ($subject_group === '') {
            $subject_group = $slug;
        }

        $statement_html = ExerciseInsertService::clean_html((string) (self::first_value($data, ['statement_html', 'intro_html', 'presentation_html', 'enonce_html', 'statement', 'presentation']) ?? ''));
        $statement_html = self::append_source_transcript($statement_html, (string) ($context['source_text'] ?? $context['pdf_text'] ?? ''));
        $fallback_meta = self::infer_source_metadata($context, $title, $slug);
        $session_label = sanitize_text_field((string) (self::first_value($data, ['session_label', 'session']) ?? ''));
        $year_label = sanitize_text_field((string) (self::first_value($data, ['year_label', 'year', 'annee']) ?? ''));
        $center_label = sanitize_text_field((string) (self::first_value($data, ['center_label', 'center', 'centre']) ?? ''));

        return [
            'title' => $title,
            'slug' => $slug,
            'statement_html' => $statement_html,
            'source_type' => (string) $context['source_type'],
            'session_label' => $session_label !== '' ? $session_label : $fallback_meta['session_label'],
            'year_label' => $year_label !== '' ? $year_label : $fallback_meta['year_label'],
            'center_label' => $center_label !== '' ? $center_label : $fallback_meta['center_label'],
            'subject_group' => $subject_group,
            'estimated_minutes' => max(1, (int) (self::first_value($data, ['estimated_minutes', 'duration_minutes', 'duree_minutes', 'duree']) ?? 210)),
            'exercises' => $exercises,
        ];
    }

    private function complete_missing_source_exercises(array $exercises, array $context): array
    {
        $source_exercises = self::split_source_exercises((string) ($context['source_text'] ?? $context['pdf_text'] ?? ''));
        if (!$source_exercises) {
            return $exercises;
        }

        $present = [];
        foreach ($exercises as $exercise) {
            $order = (int) ($exercise['exercise_order'] ?? 0);
            if ($order > 0) {
                $present[$order] = true;
            }
        }

        $changed = false;
        foreach ($source_exercises as $source_exercise) {
            $order = (int) $source_exercise['exercise_order'];
            if (isset($present[$order])) {
                continue;
            }

            $chunk = (string) $source_exercise['text'];
            $competency_ids = $this->infer_competency_ids($chunk, $context);

            $exercises[] = [
                'exercise_order' => $order,
                'title' => (string) $source_exercise['title'],
                'intro_html' => self::source_text_to_html($chunk),
                'max_points' => null,
                'questions' => [[
                    'question_order' => 1,
                    'question_label' => 'Sujet complet',
                    'prompt_html' => '<p>Traite les questions de cet exercice a partir du texte complet affiche ci-dessus.</p>',
                    'answer_type' => 'mixed',
                    'max_points' => null,
                    'competency_ids' => $competency_ids,
                    'hints' => [[
                        'rank' => 1,
                        'title' => 'Aide IA',
                        'html' => '<p>Commence par reperer les sous-questions et les donnees utiles dans le texte de l exercice, puis reponds dans l ordre.</p>',
                    ]],
                ]],
            ];
            $changed = true;
        }

        if ($changed) {
            usort($exercises, static fn(array $a, array $b): int => ((int) ($a['exercise_order'] ?? 0)) <=> ((int) ($b['exercise_order'] ?? 0)));
        }

        return $exercises;
    }

    private function normalize_ordering(array $exercises): array
    {
        $used_exercise_orders = [];

        foreach ($exercises as &$exercise) {
            $exercise['exercise_order'] = self::next_available_order(
                (int) ($exercise['exercise_order'] ?? 1),
                $used_exercise_orders
            );

            $used_question_orders = [];
            $exercise['questions'] = array_values((array) ($exercise['questions'] ?? []));
            foreach ($exercise['questions'] as &$question) {
                $question['question_order'] = self::next_available_order(
                    (int) ($question['question_order'] ?? 1),
                    $used_question_orders
                );

                $used_hint_orders = [];
                $question['hints'] = array_values((array) ($question['hints'] ?? []));
                foreach ($question['hints'] as &$hint) {
                    $hint['rank'] = self::next_available_order(
                        (int) ($hint['rank'] ?? $hint['hint_order'] ?? 1),
                        $used_hint_orders
                    );
                }
                unset($hint);
            }
            unset($question);
        }
        unset($exercise);

        return $exercises;
    }

    private static function next_available_order(int $requested, array &$used): int
    {
        $order = max(1, min(30000, $requested));
        while (isset($used[$order])) {
            $order++;
        }

        $used[$order] = true;
        return $order;
    }

    private static function split_source_exercises(string $source_text): array
    {
        $source_text = trim(str_replace(["\r\n", "\r"], "\n", $source_text));
        if ($source_text === '') {
            return [];
        }

        preg_match_all('/(?:^|\n)\s*(EXERCICE|Exercice)\s+([1-9][0-9]*)[^\n]*/u', $source_text, $matches, PREG_OFFSET_CAPTURE);
        if (empty($matches[0])) {
            return [];
        }

        $starts = [];
        foreach ($matches[0] as $index => $match) {
            $order = (int) ($matches[2][$index][0] ?? 0);
            $offset = (int) $match[1];
            if ($order <= 0 || isset($starts[$order])) {
                continue;
            }
            $starts[$order] = [
                'order' => $order,
                'offset' => $offset,
                'title' => trim((string) $match[0]),
            ];
        }

        usort($starts, static fn(array $a, array $b): int => $a['offset'] <=> $b['offset']);
        $exercises = [];
        $count = count($starts);
        for ($i = 0; $i < $count; $i++) {
            $start = $starts[$i]['offset'];
            $end = $i + 1 < $count ? $starts[$i + 1]['offset'] : strlen($source_text);
            $chunk = trim(substr($source_text, $start, max(0, $end - $start)));
            if (mb_strlen($chunk) < 120) {
                continue;
            }

            $title = sanitize_text_field($starts[$i]['title']);
            if ($title === '') {
                $title = 'Exercice ' . (int) $starts[$i]['order'];
            }

            $exercises[] = [
                'exercise_order' => (int) $starts[$i]['order'],
                'title' => $title,
                'text' => $chunk,
            ];
        }

        return $exercises;
    }

    private static function source_text_to_html(string $source_text): string
    {
        $html = '<pre><code>' . esc_html(trim($source_text)) . '</code></pre>';
        return ExerciseInsertService::clean_html($html);
    }

    private static function infer_source_metadata(array $context, string $title, string $slug): array
    {
        $haystack = implode(' ', [
            (string) ($context['source_filename'] ?? ''),
            (string) ($context['fallback_title'] ?? ''),
            $title,
            $slug,
            mb_substr((string) ($context['source_text'] ?? $context['pdf_text'] ?? ''), 0, 5000),
        ]);

        $normalized = remove_accents(strtolower($haystack));
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', (string) $normalized) ?? $normalized;
        $year = '';
        if (preg_match('/\b(20[0-9]{2})\b/', $normalized, $m)) {
            $year = $m[1];
        }

        $centers = [
            'centres etranger' => 'Centres etrangers',
            'centres etrangers' => 'Centres etrangers',
            'metropole' => 'Metropole',
            'amerique du nord' => 'Amerique du Nord',
            'asie' => 'Asie',
            'polynesie' => 'Polynesie',
            'nouvelle caledonie' => 'Nouvelle-Caledonie',
            'antilles guyane' => 'Antilles-Guyane',
            'liban' => 'Liban',
            'mayotte' => 'Mayotte',
            'reunion' => 'Reunion',
        ];

        $center = '';
        foreach ($centers as $needle => $label) {
            if (str_contains($normalized, $needle)) {
                $center = $label;
                break;
            }
        }

        $subject_number = '';
        if (preg_match('/\bsujet\s*([0-9]+)\b/', $normalized, $m)) {
            $subject_number = $m[1];
        } elseif (preg_match('/\b(?:metropole|etranger|etrangers|asie|polynesie|liban|reunion|mayotte)\s+([0-9]+)\b/', $normalized, $m)) {
            $subject_number = $m[1];
        }

        $session = trim($center . ($subject_number !== '' ? ' sujet ' . $subject_number : ''));
        if ($session === '') {
            $session = sanitize_text_field((string) ($context['fallback_title'] ?? $title));
        }

        return [
            'session_label' => $session,
            'year_label' => $year,
            'center_label' => $center,
        ];
    }

    private static function append_source_transcript(string $statement_html, string $source_text): string
    {
        $source_text = trim($source_text);
        if ($source_text === '') {
            return $statement_html;
        }

        $transcript = '<div class="ouinpo-written-source-transcript">'
            . '<h3>Texte complet extrait du sujet</h3>'
            . '<p>Ce bloc reprend le texte extrait du PDF pour que le sujet reste exploitable meme si un contexte a ete mal decoupe.</p>'
            . '<pre class="ouinpo-written-source-text">' . esc_html($source_text) . '</pre>'
            . '</div>';

        return ExerciseInsertService::clean_html(trim($statement_html . "\n" . $transcript));
    }

    private function normalize_payload(array $data): array
    {
        foreach (['subject', 'sujet', 'annale'] as $container_key) {
            if (empty($data['exercises']) && !empty($data[$container_key]) && is_array($data[$container_key])) {
                $container = $data[$container_key];
                $data = array_merge($container, $data);
            }
        }

        if (empty($data['exercises'])) {
            $data['exercises'] = self::first_value($data, ['exercices', 'items', 'sections', 'parts', 'parties']) ?? [];
        }

        if (empty($data['exercises']) && !empty($data['questions']) && is_array($data['questions'])) {
            $data['exercises'] = [[
                'exercise_order' => 1,
                'title' => 'Exercice 1',
                'questions' => $data['questions'],
            ]];
        }

        if (!is_array($data['exercises'])) {
            $data['exercises'] = [];
        }

        foreach ($data['exercises'] as $exercise_index => $exercise) {
            if (!is_array($exercise)) {
                continue;
            }

            if (empty($exercise['questions'])) {
                $exercise['questions'] = self::first_value($exercise, ['sous_questions', 'subquestions', 'items', 'questions_html']) ?? [];
            }

            if (empty($exercise['questions']) && self::first_value($exercise, ['prompt_html', 'enonce_html', 'enonce', 'statement_html', 'statement'])) {
                $exercise['questions'] = [[
                    'question_order' => 1,
                    'question_label' => '1',
                    'prompt_html' => self::first_value($exercise, ['prompt_html', 'enonce_html', 'enonce', 'statement_html', 'statement']),
                    'competency_ids' => self::first_value($exercise, ['competency_ids', 'competence_ids', 'competencies', 'competences']) ?? [],
                    'hints' => self::first_value($exercise, ['hints', 'aides', 'indices']) ?? [],
                ]];
            }

            if (!is_array($exercise['questions'])) {
                $exercise['questions'] = [[
                    'question_order' => 1,
                    'question_label' => '1',
                    'prompt_html' => (string) $exercise['questions'],
                ]];
            }

            $data['exercises'][$exercise_index] = $exercise;
        }

        return $data;
    }

    private static function first_value(array $row, array $keys)
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return $row[$key];
            }
        }

        return null;
    }

    private function extract_competency_ids(array $question): array
    {
        $raw = self::first_value($question, [
            'competency_ids',
            'competence_ids',
            'competencies',
            'competences',
            'bo_competency_ids',
            'bo_competences',
        ]) ?? [];

        if (!is_array($raw)) {
            $raw = [$raw];
        }

        $ids = [];
        foreach ($raw as $item) {
            if (is_array($item)) {
                $item = self::first_value($item, ['id', 'competency_id', 'competence_id']);
            }
            $ids[] = (int) $item;
        }

        return $ids;
    }

    private function infer_competency_ids(string $text, array $context): array
    {
        $haystack = $this->normalize_for_score($text);
        $scores = [];
        $numeric_conversion = preg_match('/\b(binaire|decimal|decimale|base|conversion|convertir|bits?|octets?|hexadecimal|representation|entiers?)\b/', $haystack) === 1;

        foreach ((array) ($context['competencies'] ?? []) as $competency) {
            $id = (int) ($competency['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $label = implode(' ', [
                (string) ($competency['domain'] ?? ''),
                (string) ($competency['domain_slug'] ?? ''),
                (string) ($competency['competency'] ?? ''),
                (string) ($competency['label'] ?? ''),
            ]);

            $needle = $this->normalize_for_score($label);
            $is_generic = preg_match('/\b(autonomie|autonome|collabor|projet|oral|documentation|transversal|organiser|communiquer)\b/', $needle) === 1;
            $tokens = array_values(array_unique(array_filter(preg_split('/\s+/', $needle) ?: [], static function ($token) {
                return strlen($token) >= 4;
            })));

            $score = 0;
            foreach ($tokens as $token) {
                if (strpos($haystack, $token) !== false) {
                    $score++;
                }
            }

            if ($numeric_conversion) {
                if (preg_match('/\b(binaire|decimal|decimale|base|bits?|octets?|hexadecimal|representation|entiers?|codage|encodage|machine|architecture|donnees?)\b/', $needle) === 1) {
                    $score += 5;
                }
                if ($is_generic) {
                    $score -= 4;
                }
            } elseif ($is_generic) {
                $score -= 2;
            }

            if ($score > 0) {
                $scores[$id] = $score;
            }
        }

        if (!$scores) {
            return [];
        }

        arsort($scores);
        return array_slice(array_map('intval', array_keys($scores)), 0, 2);
    }

    private function normalize_for_score(string $text): string
    {
        $text = html_entity_decode(wp_strip_all_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = remove_accents($text);
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9_]+/', ' ', $text) ?? $text;
        return trim($text);
    }

    private function float_or_null($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = str_replace(',', '.', $value);
        }

        return is_numeric($value) ? (float) $value : null;
    }

    private function persist(array $data, array $context): int|\WP_Error
    {
        global $wpdb;

        $p = $wpdb->prefix . 'ouin_exo_';
        $existing_subject_id = max(0, (int) ($context['existing_subject_id'] ?? 0));
        $existing_visibility = null;
        if ($existing_subject_id > 0) {
            $existing_visibility = $wpdb->get_var($wpdb->prepare(
                "SELECT is_active FROM {$p}written_subjects WHERE id = %d LIMIT 1",
                $existing_subject_id
            ));
            if ($existing_visibility === null) {
                $existing_subject_id = 0;
            }
        }

        $wpdb->query('START TRANSACTION');

        $subject_payload = [
            'title' => $data['title'],
            'slug' => $data['slug'],
            'statement' => $data['statement_html'] ?: null,
            'source_type' => $data['source_type'],
            'session_label' => $data['session_label'] ?: null,
            'year_label' => $data['year_label'] ?: null,
            'center_label' => $data['center_label'] ?: null,
            'subject_group' => $data['subject_group'],
            'estimated_minutes' => $data['estimated_minutes'],
            'is_active' => $existing_subject_id > 0 ? (int) $existing_visibility : 0,
            'updated_at' => current_time('mysql'),
        ];

        if ($existing_subject_id > 0) {
            $updated = $wpdb->update($p . 'written_subjects', $subject_payload, ['id' => $existing_subject_id]);
            if ($updated === false) {
                $wpdb->query('ROLLBACK');
                return new \WP_Error('update_failed', 'Mise a jour de l annale impossible.');
            }

            $subject_id = $existing_subject_id;
            $this->delete_existing_subject_content($subject_id);
        } else {
            $subject_payload['created_at'] = current_time('mysql');
            $inserted = $wpdb->insert($p . 'written_subjects', $subject_payload);

            $subject_id = (int) $wpdb->insert_id;
            if ($inserted === false || $subject_id <= 0) {
                $wpdb->query('ROLLBACK');
                return new \WP_Error('insert_failed', 'Creation de l annale impossible.');
            }
        }

        $wpdb->delete($p . 'written_subject_school_level', ['subject_id' => $subject_id], ['%d']);
        foreach ((array) $context['school_levels'] as $level_id) {
            $level_id = (int) $level_id;
            if ($level_id > 0) {
                $inserted = $wpdb->insert($p . 'written_subject_school_level', [
                    'subject_id' => $subject_id,
                    'school_level_id' => $level_id,
                ], ['%d', '%d']);
                if ($inserted === false) {
                    $wpdb->query('ROLLBACK');
                    return new \WP_Error('insert_failed', 'Association du niveau impossible.');
                }
            }
        }

        foreach ($data['exercises'] as $exercise) {
            $inserted = $wpdb->insert($p . 'written_exercises', [
                'subject_id' => $subject_id,
                'exercise_order' => (int) $exercise['exercise_order'],
                'title' => $exercise['title'] ?: null,
                'intro_html' => $exercise['intro_html'] ?: null,
                'max_points' => $exercise['max_points'],
                'is_active' => 1,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ]);
            $exercise_id = (int) $wpdb->insert_id;
            if ($inserted === false || $exercise_id <= 0) {
                $wpdb->query('ROLLBACK');
                return new \WP_Error('insert_failed', 'Creation d un exercice impossible.');
            }

            foreach ($exercise['questions'] as $question) {
                $inserted = $wpdb->insert($p . 'written_questions', [
                    'exercise_id' => $exercise_id,
                    'question_order' => (int) $question['question_order'],
                    'question_label' => $question['question_label'],
                    'prompt_html' => $question['prompt_html'],
                    'answer_type' => $question['answer_type'],
                    'max_points' => $question['max_points'],
                    'is_active' => 1,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql'),
                ]);
                $question_id = (int) $wpdb->insert_id;
                if ($inserted === false || $question_id <= 0) {
                    $wpdb->query('ROLLBACK');
                    return new \WP_Error('insert_failed', 'Creation d une question impossible.');
                }

                foreach ($question['competency_ids'] as $competency_id) {
                    $inserted = $wpdb->insert($p . 'written_question_competency', [
                        'question_id' => $question_id,
                        'competency_id' => (int) $competency_id,
                    ], ['%d', '%d']);
                    if ($inserted === false) {
                        $wpdb->query('ROLLBACK');
                        return new \WP_Error('insert_failed', 'Association de competence impossible.');
                    }
                }

                foreach ($question['hints'] as $hint) {
                    $inserted = $wpdb->insert($p . 'written_question_hints', [
                        'question_id' => $question_id,
                        'hint_order' => (int) $hint['rank'],
                        'title' => $hint['title'] ?: null,
                        'content' => $hint['html'],
                        'is_ai' => 1,
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql'),
                    ]);
                    if ($inserted === false) {
                        $wpdb->query('ROLLBACK');
                        return new \WP_Error('insert_failed', 'Creation d une aide IA impossible.');
                    }
                }
            }
        }

        $wpdb->query('COMMIT');

        return $subject_id;
    }

    private function delete_existing_subject_content(int $subject_id): void
    {
        if ($subject_id <= 0) {
            return;
        }

        global $wpdb;

        $p = $wpdb->prefix . 'ouin_exo_';
        $exercise_ids = array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$p}written_exercises WHERE subject_id = %d",
            $subject_id
        )) ?: []);

        if (!$exercise_ids) {
            return;
        }

        $exercise_placeholders = implode(',', array_fill(0, count($exercise_ids), '%d'));
        $question_ids = array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM {$p}written_questions WHERE exercise_id IN ({$exercise_placeholders})",
            $exercise_ids
        )) ?: []);

        if ($question_ids) {
            $question_placeholders = implode(',', array_fill(0, count($question_ids), '%d'));
            foreach (['written_question_answers', 'written_hint_usage', 'written_question_status', 'written_question_competency', 'written_question_hints'] as $suffix) {
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM {$p}{$suffix} WHERE question_id IN ({$question_placeholders})",
                    $question_ids
                ));
            }
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$p}written_questions WHERE id IN ({$question_placeholders})",
                $question_ids
            ));
        }

        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$p}written_exercises WHERE id IN ({$exercise_placeholders})",
            $exercise_ids
        ));
    }

    private function unique_slug(string $slug, int $exclude_subject_id = 0): string
    {
        global $wpdb;

        $table = $wpdb->prefix . 'ouin_exo_written_subjects';
        $base = $slug !== '' ? $slug : 'annale-nsi';
        $candidate = $base;
        $i = 2;

        while ((int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE slug = %s AND id <> %d",
            $candidate,
            $exclude_subject_id
        )) > 0) {
            $candidate = $base . '-' . $i;
            $i++;
        }

        return $candidate;
    }
}
