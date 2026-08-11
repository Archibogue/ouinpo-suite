<?php

namespace Ouinpo\Suite\Core;

defined('ABSPATH') || exit;

final class AiSettings
{
    public const DEFAULT_PROVIDER = 'albert';
    public const PROVIDERS = ['albert', 'openai'];
    public const SECRET_UNCHANGED_PLACEHOLDER = '__ouinpo_secret_unchanged__';

    private static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;
        add_action('admin_init', [self::class, 'register_settings']);
        add_action('admin_notices', [self::class, 'render_public_access_migration_notice']);
    }

    public static function defaults(): array
    {
        return array_merge([
            'ouinpo_ai_enabled' => 0,
            'ouinpo_ai_public_enabled' => 0,
            'ouinpo_ai_usage_chat_rag' => 1,
            'ouinpo_ai_usage_exercise_help' => 1,
            'ouinpo_ai_usage_exercise_correction' => 1,
            'ouinpo_ai_usage_written_subject_answers' => 0,
            'ouinpo_ai_usage_written_subject_report' => 0,
            'ouinpo_ai_usage_gate_validation' => 0,
            'ouinpo_ai_usage_practical_correction' => 1,
            'ouinpo_ai_usage_feedback_generation' => 1,
            'ouinpo_ai_usage_pedagogical_suggestions' => 1,
            'ouinpo_projects_student_ai_enabled' => 0,
            'ouinpo_ai_correction_scans_enabled' => 0,
            'ouinpo_ai_correction_keep_scans' => 1,
            'ouinpo_ai_correction_max_file_mb' => 12,
            'ouinpo_ai_file_correction_enabled' => 0,
            'ouinpo_ai_file_correction_keep_files' => 1,
            'ouinpo_ai_file_correction_max_file_mb' => 8,
            'ouinpo_ai_file_correction_retention_days' => 30,
            'ouinpo_ai_default_provider' => self::DEFAULT_PROVIDER,
            'ouinpo_ai_public_provider' => 'albert',
            'ouinpo_ai_logged_provider' => 'albert',
            'ouinpo_ai_api_base_url' => 'https://albert.api.etalab.gouv.fr/v1',
            'ouinpo_ai_api_key' => '',
            'ouinpo_ai_ocr_provider' => 'albert',
            'ouinpo_ai_chat_model' => 'openai/gpt-oss-120b',
            'ouinpo_ai_code_model' => 'openweight-code',
            'ouinpo_ai_embedding_model' => 'BAAI/bge-m3',
            'ouinpo_ai_ocr_model' => '',
            'ouinpo_ai_timeout' => 45,
            'ouinpo_ai_max_tokens' => 800,
            'ouinpo_ai_temperature' => 0.3,
            'ouinpo_ai_top_p' => 1.0,
            'ouinpo_ai_frequency_penalty' => 0.0,
            'ouinpo_ai_presence_penalty' => 0.0,
            'ouinpo_ai_user_daily_limit' => 200,
            'ouinpo_ai_public_daily_limit' => 10,
            'ouinpo_ai_public_ip_per_minute' => 5,
            'ouinpo_ai_public_ip_per_day' => 100,
            'ouinpo_ai_public_global_per_minute' => 40,
            'ouinpo_ai_public_global_per_day' => 4000,
            'ouinpo_ai_student_per_minute' => 15,
            'ouinpo_ai_student_per_day' => 300,
            'ouinpo_ai_exercise_ai_per_minute' => 5,
            'ouinpo_ai_exercise_ai_per_day' => 120,
            'ouinpo_ai_practical_ai_per_minute' => 5,
            'ouinpo_ai_practical_ai_per_day' => 80,
            'ouinpo_ai_teacher_per_minute' => 30,
            'ouinpo_ai_teacher_per_day' => 1000,
            'ouinpo_ai_projects_student_per_minute' => 3,
            'ouinpo_ai_projects_student_per_day' => 10,
            'ouinpo_ai_public_chat_max_tokens' => 900,
            'ouinpo_ai_exercise_ai_max_tokens' => 800,
            'ouinpo_ai_practical_ai_max_tokens' => 1200,
            'ouinpo_ai_projects_student_max_tokens' => 1200,
            'ouinpo_ai_public_rag_max_tokens' => 1200,
            'ouinpo_ai_disabled_message' => 'L\'assistant IA est desactive pour le moment.',
            'ouinpo_ai_privacy_notice' => 'IA pedagogique : n\'ecris pas de donnees personnelles. Les reponses peuvent contenir des erreurs et doivent etre verifiees.',
            'ouinpo_ai_chatbox_welcome_message' => 'Miaou. Je suis *SegFault* - ton assistant NSI. Pose ta question sur Python, algorithmique, structures de donnees, reseaux, bases ou web. Hors-sujet ? Je t indiquerai un cours NSI a la place.',
        ], self::persona_defaults(), [
            'ouinpo_ai_rag_system_prompt' => self::default_rag_prompt(),
            'ouinpo_ai_exercise_correction_prompt' => self::default_exercise_prompt(),
            'ouinpo_ai_practical_correction_prompt' => self::default_practical_prompt(),
            'ouinpo_ai_suggestions_prompt' => 'Propose des suggestions pedagogiques courtes, adaptees au niveau et aux competences disponibles. N\'invente pas de ressource absente du contexte.',
            'ouinpo_ai_out_of_program_guardrails' => self::default_guardrails(),
            'ouinpo_ai_anonymous_default_school_level' => 'premiere',
            'ouinpo_ai_show_rag_sources' => 1,
            'ouinpo_ai_debug_logs' => 0,
            'ouinpo_public_exercises_enabled' => 0,
            'ouinpo_public_hints_enabled' => 0,
            'ouinpo_public_solutions_enabled' => 0,
            'ouinpo_public_practical_subjects_enabled' => 0,
            'ouinpo_public_practical_files_enabled' => 0,
            'ouinpo_public_written_subjects_enabled' => 0,
            'ouinpo_public_written_files_enabled' => 0,
            'ouinpo_public_written_answer_zones_enabled' => 0,
            'ouinpo_public_written_ai_enabled' => 0,
            'ouinpo_sf_openai_api_key' => '',
            'ouinpo_sf_model' => 'gpt-5-mini',
            'ouinpo_sf_embed_model' => 'text-embedding-3-large',
            'ouinpo_sf_memory_days' => 30,
            'ouinpo_sf_members_only' => 0,
            'ouinpo_sf_wxr_path' => '',
            'ouinpo_sf_albert_enabled' => 0,
            'ouinpo_sf_public_albert_enabled' => 0,
            'ouinpo_sf_albert_api_key' => '',
            'ouinpo_sf_albert_base_url' => 'https://albert.api.etalab.gouv.fr/v1',
            'ouinpo_sf_albert_model' => 'openai/gpt-oss-120b',
            'ouinpo_sf_albert_code_model' => 'openweight-code',
            'ouinpo_sf_albert_embedding_model' => 'BAAI/bge-m3',
            'ouinpo_sf_albert_reranker_model' => 'BAAI/bge-reranker-v2-m3',
            'ouinpo_sf_albert_ocr_model' => '',
            'ouinpo_sf_rag_embedding_provider' => 'openai',
            'ouinpo_sf_public_hourly_limit' => 5,
            'ouinpo_sf_public_daily_limit' => 100,
            'ouinpo_sf_rag_rerank_candidates' => 40,
            'ouinpo_sf_max_embeddings_run' => 120,
            'ouinpo_sf_ai_notice_url' => '',
            'ouinpo_sf_ai_notice_public' => 'Assistant IA public : n\'ecris pas de nom, prenom, note, adresse ou information personnelle. Les reponses peuvent contenir des erreurs.',
            'ouinpo_sf_ai_notice_logged' => 'IA pedagogique : n\'ecris pas de donnees personnelles. Les reponses proposees par l\'assistant doivent etre verifiees et ne remplacent pas le professeur.',
        ]);
    }

    public static function register_settings(string $group = 'ouinpo_sf'): void
    {
        foreach (self::schema() as $option => $type) {
            $sanitizeCallback = $type === 'secret'
                ? static fn($value): string => self::sanitize_secret_option($option, $value)
                : [self::class, 'sanitize_' . $type];

            register_setting($group, $option, [
                'default' => self::defaults()[$option] ?? '',
                'sanitize_callback' => $sanitizeCallback,
            ]);
        }
    }

    public static function get(string $option)
    {
        if (isset(self::secretConstants()[$option])) {
            return self::secret($option);
        }

        $defaults = self::defaults();
        return get_option($option, $defaults[$option] ?? '');
    }

    public static function persona_options(): array
    {
        $options = [];
        foreach (self::persona_definitions() as $option => $definition) {
            $definition['option'] = (string) ($definition['option'] ?? $option);
            $definition['category'] = (string) ($definition['category'] ?? 'persona');
            $definition['rows'] = (int) ($definition['rows'] ?? 4);
            $options[$option] = $definition;
        }

        return $options;
    }

    public static function user_message_options(): array
    {
        return [
            'ouinpo_ai_disabled_message' => [
                'label' => 'Message IA desactivee',
                'category' => 'user_message',
                'description' => 'Texte affiche quand l assistant IA est indisponible ou desactive.',
                'help' => 'Texte visible directement dans l interface. Il ne modifie pas le comportement interne de l IA.',
                'rows' => 1,
            ],
            'ouinpo_ai_privacy_notice' => [
                'label' => 'Information RGPD / usage pedagogique',
                'category' => 'user_message',
                'description' => 'Notice generale affichee autour des usages IA pedagogiques.',
                'help' => 'Texte visible par les utilisateurs. Garder une formulation courte, claire et non technique.',
                'rows' => 3,
            ],
            'ouinpo_ai_chatbox_welcome_message' => [
                'label' => 'Message d accueil de la chatbox',
                'category' => 'user_message',
                'description' => 'Premier message affiche dans la bulle SegFault quand la conversation est vide.',
                'help' => 'Texte visible dans la chatbox. Le Markdown simple est accepte, par exemple *SegFault*.',
                'rows' => 4,
            ],
            'ouinpo_sf_ai_notice_url' => [
                'label' => 'URL de la page d information IA',
                'category' => 'user_message',
                'description' => 'Lien affiche sous les messages d information IA.',
                'help' => 'Peut etre vide, une URL complete ou un chemin relatif du site.',
                'rows' => 1,
            ],
            'ouinpo_sf_ai_notice_public' => [
                'label' => 'Message IA publique',
                'category' => 'user_message',
                'description' => 'Message affiche aux visiteurs non connectes avant ou pres du chat public.',
                'help' => 'Texte visible directement dans l interface publique.',
                'rows' => 4,
            ],
            'ouinpo_sf_ai_notice_logged' => [
                'label' => 'Message IA eleves connectes',
                'category' => 'user_message',
                'description' => 'Message affiche aux utilisateurs connectes.',
                'help' => 'Texte visible directement dans l interface connectee.',
                'rows' => 4,
            ],
        ];
    }

    public static function internal_instruction_options(): array
    {
        return [
            'ouinpo_ai_rag_system_prompt' => [
                'label' => 'Regles RAG',
                'category' => 'internal_instruction',
                'description' => 'Consigne systeme pour l utilisation du contexte documentaire RAG.',
                'help' => 'Attention : ce champ pilote les sources, citations et limites documentaires de l IA.',
                'rows' => 4,
            ],
            'ouinpo_ai_exercise_correction_prompt' => [
                'label' => 'Prompt de correction JSON - exercices',
                'category' => 'internal_instruction',
                'description' => 'Consigne interne de correction des reponses aux exercices.',
                'help' => 'Attention : une modification incorrecte peut casser le JSON, les verdicts ou les garde-fous de correction.',
                'rows' => 4,
            ],
            'ouinpo_ai_practical_correction_prompt' => [
                'label' => 'Prompt de correction JSON - pratique / code',
                'category' => 'internal_instruction',
                'description' => 'Consigne interne de correction des sujets pratiques et du code.',
                'help' => 'Attention : garder les contraintes de JSON, prudence, analyse statique et non-invention.',
                'rows' => 4,
            ],
            'ouinpo_ai_suggestions_prompt' => [
                'label' => 'Suggestions pedagogiques',
                'category' => 'internal_instruction',
                'description' => 'Consigne interne pour les suggestions pedagogiques.',
                'help' => 'A modifier avec prudence : ce champ cadre les suggestions produites par l IA.',
                'rows' => 3,
            ],
            'ouinpo_ai_out_of_program_guardrails' => [
                'label' => 'Garde-fous hors programme',
                'category' => 'internal_instruction',
                'description' => 'Regles de prudence quand une demande depasse le niveau scolaire attendu.',
                'help' => 'Garde-fou important : eviter de presenter des notions hors programme comme exigibles.',
                'rows' => 4,
            ],
        ];
    }

    public static function ai_context_map(): array
    {
        return [
            [
                'usage' => 'Chatbox connectee',
                'persona' => 'chatbox',
                'context' => 'Question de l eleve, page courante si disponible, extraits RAG autorises, historique court de conversation, contexte pedagogique eleve si disponible.',
                'excluded' => 'Pas de corrige complet injecte automatiquement. Pas de documents non autorises.',
                'guardrails' => 'RAG, limites programme, anti-invention, respect des droits d acces.',
            ],
            [
                'usage' => 'Chatbox publique',
                'persona' => 'public + regles publiques strictes',
                'context' => 'Question du visiteur, page publique courante, extraits RAG publics si disponibles.',
                'excluded' => 'Pas de profil eleve, pas de progression personnelle, pas de donnees privees, pas d historique durable.',
                'guardrails' => 'Regles publiques, RGPD, anti-invention, limitation aux contenus publics.',
            ],
            [
                'usage' => 'Correction d exercice',
                'persona' => 'exercise_correction + consignes de correction',
                'context' => 'Enonce, corriges de reference, indices, reponse eleve, blocs de code eventuels, analyse syntaxique si disponible, contexte pedagogique structure : niveau attendu, niveau de reference eleve ou classe, cycle, ordre du niveau dans le cycle, competences et extraits RAG de cours/programme si disponibles.',
                'excluded' => 'Pas de comparaison par slugs historiques. Le niveau de reference adapte le feedback mais ne remplace pas le niveau attendu de l exercice.',
                'guardrails' => 'JSON strict, verdict controle, feedback pedagogique, anti-corrige complet, correction selon le niveau attendu configure.',
            ],
            [
                'usage' => 'Correction pratique / code',
                'persona' => 'practical_correction + consignes pratiques',
                'context' => 'Sujet pratique, appel concerne, consigne, grille IA eventuelle, code ou texte fourni par l eleve, contexte pedagogique structure : niveau attendu, cycle, ordre dans le cycle, niveau de reference, competences et extraits RAG de cours/programme si disponibles.',
                'excluded' => 'L IA ne doit pas pretendre avoir execute le code si aucun moteur d execution reel n est utilise.',
                'guardrails' => 'Analyse statique, JSON strict, criteres de validation, feedback exploitable, correction selon le niveau attendu configure.',
            ],
            [
                'usage' => 'Correction copie / scan OCR',
                'persona' => 'copy_correction',
                'context' => 'Devoir, bareme, exercices, solutions de reference, competences liees, texte OCR ou texte saisi, reference anonymisee de la copie, contexte pedagogique par item : niveau attendu, niveau eleve ou classe, cycle, ordre du niveau dans le cycle et extraits RAG de cours/programme si disponibles.',
                'excluded' => 'L IA produit une proposition de correction. Validation enseignant recommandee.',
                'guardrails' => 'Anti-invention, prudence OCR, anonymisation, bareme, justification, pas de penalite automatique quand un item est volontairement plus avance.',
            ],
            [
                'usage' => 'Correction fichier numerique',
                'persona' => 'copy_correction ou consigne de correction fichier selon le contexte choisi',
                'context' => 'Contexte choisi par l enseignant : devoir, exercice ou sujet pratique ; manifeste des fichiers ; contenu extrait ; avertissements d extraction ; contexte pedagogique par item avec niveau attendu, niveau de reference, cycle, ordre dans le cycle, competences et RAG de cours/programme si disponibles.',
                'excluded' => 'L IA ne doit pas pretendre avoir lance les fichiers. Le mode free reste limite par l etat reel de l implementation.',
                'guardrails' => 'Analyse statique, limites d extraction, anti-invention, validation enseignant, correction selon le niveau attendu configure.',
            ],
            [
                'usage' => 'Annales / sujets ecrits',
                'persona' => 'written_subject',
                'context' => 'Sujet, exercice, question, reponse eleve, reponses precedentes du meme exercice, aides utilisees, competences liees, extraits RAG de cours, contexte pedagogique eleve et contexte pedagogique structure : niveau attendu, cycle, ordre dans le cycle et niveau de reference.',
                'excluded' => 'L IA ne doit pas resoudre tout le sujet a la place de l eleve.',
                'guardrails' => 'Anti-corrige complet, progressivite, aide sans substitution, coherence avec le programme configure par cycle et ordre des niveaux.',
            ],
            [
                'usage' => 'Generation d exercices / devoirs / imports',
                'persona' => 'assessment_generation',
                'context' => 'Niveau, competences, domaine, difficulte, duree, contraintes enseignant, exercices candidats, KPI agreges de classe si disponibles, texte extrait d un PDF en cas d import.',
                'excluded' => 'Pas de donnees nominatives inutiles. Les KPI doivent rester agreges.',
                'guardrails' => 'Coherence pedagogique, format attendu, anti-invention, validation enseignant.',
            ],
            [
                'usage' => 'Projects enseignant',
                'persona' => 'projects_teacher',
                'context' => 'Projet complet, description, membres, taches, colonnes Kanban, livrables, traces, logs recents, competences disponibles, contexte libre enseignant.',
                'excluded' => 'L IA propose, mais ne doit pas modifier automatiquement le projet sans validation.',
                'guardrails' => 'Validation enseignant, pas d invention de traces, respect des roles.',
            ],
            [
                'usage' => 'Projects eleve / portfolio',
                'persona' => 'projects_student',
                'context' => 'Projet filtre cote eleve, ses taches, ses traces, son journal, ses livrables, competences liees, declaration personnelle.',
                'excluded' => 'L IA ne doit pas inventer du travail realise. Elle aide a formuler, pas a falsifier.',
                'guardrails' => 'Pas d invention de preuves, aide a la reformulation, honnetete portfolio.',
            ],
            [
                'usage' => 'Gate - validation d enigmes',
                'persona' => 'Prompt Gate dedie configurable',
                'context' => 'Enonce de l enigme, reponse attendue, variantes acceptees, criteres IA, niveau, theme, reponse eleve.',
                'excluded' => 'Ne pas reveler la reponse attendue.',
                'guardrails' => 'Anti-divulgation, validation stricte, format de reponse specifique.',
            ],
        ];
    }

    public static function persona(string $key, ?string $legacyOption = null, string $fallback = ''): string
    {
        $option = self::resolve_persona_option($key);
        $configured = trim((string) get_option($option, ''));
        if ($configured !== '') {
            return $configured;
        }

        if ($legacyOption !== null && $legacyOption !== $option) {
            $legacy = trim((string) get_option($legacyOption, ''));
            if ($legacy !== '') {
                return $legacy;
            }
        }

        $defaults = self::defaults();
        $default = trim((string) ($defaults[$option] ?? ''));
        if ($default !== '') {
            return $default;
        }

        if ($legacyOption !== null && $legacyOption !== $option) {
            $legacyDefault = trim((string) ($defaults[$legacyOption] ?? ''));
            if ($legacyDefault !== '') {
                return $legacyDefault;
            }
        }

        return trim($fallback);
    }

    public static function prompt(string $option, string $fallback = ''): string
    {
        $configured = trim((string) get_option($option, ''));
        if ($configured !== '') {
            return $configured;
        }

        $defaults = self::defaults();
        $default = trim((string) ($defaults[$option] ?? ''));
        return $default !== '' ? $default : trim($fallback);
    }

    public static function secret(string $option): string
    {
        $constants = self::secretConstants();
        foreach ($constants[$option] ?? [] as $constant) {
            if (defined($constant)) {
                $value = trim((string) constant($constant));
                if ($value !== '') {
                    return $value;
                }
            }
        }

        return trim((string) get_option($option, self::defaults()[$option] ?? ''));
    }

    public static function secret_configured(string $option): bool
    {
        return self::secret($option) !== '';
    }

    public static function secret_uses_constant(string $option): bool
    {
        $constants = self::secretConstants();
        foreach ($constants[$option] ?? [] as $constant) {
            if (defined($constant) && trim((string) constant($constant)) !== '') {
                return true;
            }
        }

        return false;
    }

    public static function secret_input_value(string $option): string
    {
        return self::secret_configured($option) ? self::SECRET_UNCHANGED_PLACEHOLDER : '';
    }

    public static function secret_status_label(string $option): string
    {
        if (self::secret_uses_constant($option)) {
            return 'Presente via constante wp-config.php, valeur masquee';
        }

        return self::secret_configured($option) ? 'Presente, valeur masquee' : 'Absente';
    }

    public static function enabled_for_usage(string $usage): bool
    {
        if ((int) self::get('ouinpo_ai_enabled') !== 1) {
            return false;
        }

        return (int) self::get('ouinpo_ai_usage_' . $usage) === 1;
    }

    public static function public_enabled(): bool
    {
        return (int) self::get('ouinpo_ai_enabled') === 1
            && (int) self::get('ouinpo_ai_public_enabled') === 1;
    }

    public static function public_access_enabled(string $option): bool
    {
        return (int) self::get($option) === 1;
    }

    public static function public_written_subjects_enabled(): bool
    {
        return self::public_access_enabled('ouinpo_public_written_subjects_enabled');
    }

    public static function public_written_files_enabled(): bool
    {
        return self::public_written_subjects_enabled()
            && self::public_access_enabled('ouinpo_public_written_files_enabled');
    }

    public static function public_written_answer_zones_enabled(): bool
    {
        return self::public_written_subjects_enabled()
            && self::public_access_enabled('ouinpo_public_written_answer_zones_enabled');
    }

    public static function public_written_ai_enabled(): bool
    {
        return self::public_enabled()
            && self::enabled_for_usage('written_subject_answers')
            && self::public_written_answer_zones_enabled()
            && self::public_access_enabled('ouinpo_public_written_ai_enabled');
    }

    public static function debug_logs_enabled(): bool
    {
        return defined('WP_DEBUG') && WP_DEBUG && (int) self::get('ouinpo_ai_debug_logs') === 1;
    }

    public static function debug_log(string $message, array $context = []): void
    {
        if (!self::debug_logs_enabled()) {
            return;
        }

        $safe = [];
        foreach ($context as $key => $value) {
            if (preg_match('/key|secret|token|prompt|response|answer|content/i', (string) $key)) {
                continue;
            }
            $safe[] = sanitize_key((string) $key) . '=' . substr(sanitize_text_field((string) $value), 0, 120);
        }

        error_log('[OuInPo IA/RAG] ' . sanitize_text_field($message) . ($safe ? ' | ' . implode(' ', $safe) : ''));
    }

    public static function migrate_public_access_for_existing_site(string $installed_version): void
    {
        if (version_compare($installed_version, '0.5.0', '>=')) {
            return;
        }

        $legacyPublicOptions = [
            'ouinpo_sf_public_albert_enabled',
        ];

        $legacyExplicitOptIn = false;
        foreach ($legacyPublicOptions as $legacyOption) {
            if ((int) get_option($legacyOption, 0) === 1) {
                $legacyExplicitOptIn = true;
                break;
            }
        }

        foreach ([
            'ouinpo_public_exercises_enabled',
            'ouinpo_public_hints_enabled',
            'ouinpo_public_solutions_enabled',
            'ouinpo_public_practical_subjects_enabled',
            'ouinpo_public_practical_files_enabled',
        ] as $option) {
            if (get_option($option, null) === null) {
                update_option($option, $legacyExplicitOptIn ? 1 : 0, false);
            }
        }

        if (get_option('ouinpo_ai_public_enabled', null) === null) {
            update_option('ouinpo_ai_public_enabled', $legacyExplicitOptIn ? 1 : 0, false);
        }

        update_option('ouinpo_public_access_migration_notice', 1, false);
    }

    public static function consumePublicRateLimit(
        string $scope,
        int $minuteLimit,
        int $dailyLimit,
        int $globalDailyLimit = 0,
        int $globalMinuteLimit = 0
    ) {
        if (is_user_logged_in()) {
            return true;
        }

        $scope = sanitize_key($scope);
        if ($scope === '') {
            $scope = 'public_ai';
        }

        $hash = self::publicClientHash();
        $minuteLimit = max(1, min(10000, $minuteLimit));
        $dailyLimit = max(1, min(10000, $dailyLimit));
        $globalDailyLimit = max(0, min(100000, $globalDailyLimit));
        $globalMinuteLimit = max(0, min(100000, $globalMinuteLimit));

        $minuteKey = 'ouinpo_rl_' . $scope . '_m_' . gmdate('YmdHi') . '_' . $hash;
        $dayKey = 'ouinpo_rl_' . $scope . '_d_' . gmdate('Ymd') . '_' . $hash;
        $globalMinuteKey = 'ouinpo_rl_public_ai_gm_' . gmdate('YmdHi');
        $globalDayKey = 'ouinpo_rl_public_ai_gd_' . gmdate('Ymd');

        $minuteUsed = (int) get_transient($minuteKey);
        if ($minuteUsed >= $minuteLimit) {
            return new \WP_Error(
                'ouinpo_public_quota_minute',
                'Limite atteinte pour cette minute. Réessaie un peu plus tard.',
                ['status' => 429]
            );
        }

        $dayUsed = (int) get_transient($dayKey);
        if ($dayUsed >= $dailyLimit) {
            return new \WP_Error(
                'ouinpo_public_quota_day',
                'Limite quotidienne atteinte pour cet accès public.',
                ['status' => 429]
            );
        }

        if ($globalMinuteLimit > 0) {
            $globalMinuteUsed = (int) get_transient($globalMinuteKey);
            if ($globalMinuteUsed >= $globalMinuteLimit) {
                return new \WP_Error(
                    'ouinpo_public_quota_global_minute',
                    'Limite globale atteinte pour cette minute.',
                    ['status' => 429]
                );
            }
        }

        if ($globalDailyLimit > 0) {
            $globalDayUsed = (int) get_transient($globalDayKey);
            if ($globalDayUsed >= $globalDailyLimit) {
                return new \WP_Error(
                    'ouinpo_public_quota_global_day',
                    'Limite quotidienne globale atteinte pour ce service public.',
                    ['status' => 429]
                );
            }
        }

        set_transient($minuteKey, $minuteUsed + 1, MINUTE_IN_SECONDS + 30);
        set_transient($dayKey, $dayUsed + 1, DAY_IN_SECONDS + 120);

        if ($globalMinuteLimit > 0) {
            set_transient($globalMinuteKey, ((int) get_transient($globalMinuteKey)) + 1, MINUTE_IN_SECONDS + 30);
        }

        if ($globalDailyLimit > 0) {
            set_transient($globalDayKey, ((int) get_transient($globalDayKey)) + 1, DAY_IN_SECONDS + 120);
        }

        return true;
    }

    public static function publicClientHash(): string
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
        $ip = preg_replace('/[^0-9a-fA-F:\.]/', '', $ip) ?: 'unknown';
        $salt = function_exists('wp_salt') ? wp_salt('auth') : (defined('AUTH_SALT') ? AUTH_SALT : 'ouinpo');

        return substr(hash('sha256', $ip . '|' . $salt), 0, 24);
    }

    public static function consumeUserRateLimit(
        string $scope,
        int $userId,
        int $minuteLimit,
        int $dailyLimit
    ) {
        if ($userId <= 0) {
            return true;
        }

        $scope = sanitize_key($scope);
        if ($scope === '') {
            $scope = 'student_ai';
        }

        $minuteLimit = max(1, min(10000, $minuteLimit));
        $dailyLimit = max(1, min(10000, $dailyLimit));
        $salt = function_exists('wp_salt') ? wp_salt('auth') : (defined('AUTH_SALT') ? AUTH_SALT : 'ouinpo');
        $userHash = substr(hash('sha256', (string) $userId . '|' . $salt), 0, 24);

        $minuteKey = 'ouinpo_rl_' . $scope . '_um_' . gmdate('YmdHi') . '_' . $userHash;
        $dayKey = 'ouinpo_rl_' . $scope . '_ud_' . gmdate('Ymd') . '_' . $userHash;

        $minuteUsed = (int) get_transient($minuteKey);
        if ($minuteUsed >= $minuteLimit) {
            return new \WP_Error(
                'ouinpo_user_quota_minute',
                'Limite atteinte pour cette minute. Réessaie un peu plus tard.',
                ['status' => 429]
            );
        }

        $dayUsed = (int) get_transient($dayKey);
        if ($dayUsed >= $dailyLimit) {
            return new \WP_Error(
                'ouinpo_user_quota_day',
                'Limite quotidienne atteinte pour cet usage IA.',
                ['status' => 429]
            );
        }

        set_transient($minuteKey, $minuteUsed + 1, MINUTE_IN_SECONDS + 30);
        set_transient($dayKey, $dayUsed + 1, DAY_IN_SECONDS + 120);

        return true;
    }

    public static function quota(string $option): int
    {
        $defaults = self::defaults();
        return self::sanitize_quota(get_option($option, $defaults[$option] ?? 0));
    }

    public static function maxTokens(string $option): int
    {
        $defaults = self::defaults();
        return self::sanitize_max_tokens(get_option($option, $defaults[$option] ?? 800));
    }

    public static function currentUserUsesTeacherAiQuota(): bool
    {
        return current_user_can('manage_options')
            || Capabilities::can(Capabilities::MANAGE_AI)
            || Capabilities::can(Capabilities::MANAGE_EXERCISES)
            || Capabilities::can(Capabilities::MANAGE_PRACTICAL_SUBJECTS);
    }

    public static function render_public_access_migration_notice(): void
    {
        if ((int) get_option('ouinpo_public_access_migration_notice', 0) !== 1) {
            return;
        }

        if (!current_user_can(Capabilities::MANAGE_SETTINGS) && !current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['ouinpo_dismiss_public_access_notice'])) {
            delete_option('ouinpo_public_access_migration_notice');
            return;
        }

        $dismissUrl = add_query_arg(
            ['ouinpo_dismiss_public_access_notice' => '1'],
            admin_url('admin.php?page=ouinpo-suite-settings&tab=ai')
        );

        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <strong>OuInPo Suite :</strong>
                les accès publics aux exercices, indices, solutions, fichiers pratiques et à l'IA restent désactivés par défaut après migration.
                Vérifiez volontairement ces réglages avant d'ouvrir le site à des visiteurs anonymes.
                <a href="<?php echo esc_url($dismissUrl); ?>">Masquer cet avis</a>
            </p>
        </div>
        <?php
    }

    private static function schema(): array
    {
        return array_merge([
            'ouinpo_ai_enabled' => 'bool',
            'ouinpo_ai_public_enabled' => 'bool',
            'ouinpo_ai_usage_chat_rag' => 'bool',
            'ouinpo_ai_usage_exercise_help' => 'bool',
            'ouinpo_ai_usage_exercise_correction' => 'bool',
            'ouinpo_ai_usage_written_subject_answers' => 'bool',
            'ouinpo_ai_usage_written_subject_report' => 'bool',
            'ouinpo_ai_usage_gate_validation' => 'bool',
            'ouinpo_ai_usage_practical_correction' => 'bool',
            'ouinpo_ai_usage_feedback_generation' => 'bool',
            'ouinpo_ai_usage_pedagogical_suggestions' => 'bool',
            'ouinpo_projects_student_ai_enabled' => 'bool',
            'ouinpo_ai_correction_scans_enabled' => 'bool',
            'ouinpo_ai_correction_keep_scans' => 'bool',
            'ouinpo_ai_correction_max_file_mb' => 'quota',
            'ouinpo_ai_file_correction_enabled' => 'bool',
            'ouinpo_ai_file_correction_keep_files' => 'bool',
            'ouinpo_ai_file_correction_max_file_mb' => 'quota',
            'ouinpo_ai_file_correction_retention_days' => 'quota',
            'ouinpo_ai_default_provider' => 'provider',
            'ouinpo_ai_public_provider' => 'provider',
            'ouinpo_ai_logged_provider' => 'provider',
            'ouinpo_ai_api_base_url' => 'url',
            'ouinpo_ai_api_key' => 'secret',
            'ouinpo_ai_ocr_provider' => 'ocr_provider',
            'ouinpo_ai_chat_model' => 'model',
            'ouinpo_ai_code_model' => 'model',
            'ouinpo_ai_embedding_model' => 'model',
            'ouinpo_ai_ocr_model' => 'model',
            'ouinpo_ai_timeout' => 'timeout',
            'ouinpo_ai_max_tokens' => 'max_tokens',
            'ouinpo_ai_temperature' => 'temperature',
            'ouinpo_ai_top_p' => 'top_p',
            'ouinpo_ai_frequency_penalty' => 'penalty',
            'ouinpo_ai_presence_penalty' => 'penalty',
            'ouinpo_ai_user_daily_limit' => 'quota',
            'ouinpo_ai_public_daily_limit' => 'quota',
            'ouinpo_ai_public_ip_per_minute' => 'quota',
            'ouinpo_ai_public_ip_per_day' => 'quota',
            'ouinpo_ai_public_global_per_minute' => 'quota',
            'ouinpo_ai_public_global_per_day' => 'quota',
            'ouinpo_ai_student_per_minute' => 'quota',
            'ouinpo_ai_student_per_day' => 'quota',
            'ouinpo_ai_exercise_ai_per_minute' => 'quota',
            'ouinpo_ai_exercise_ai_per_day' => 'quota',
            'ouinpo_ai_practical_ai_per_minute' => 'quota',
            'ouinpo_ai_practical_ai_per_day' => 'quota',
            'ouinpo_ai_teacher_per_minute' => 'quota',
            'ouinpo_ai_teacher_per_day' => 'quota',
            'ouinpo_ai_projects_student_per_minute' => 'quota',
            'ouinpo_ai_projects_student_per_day' => 'quota',
            'ouinpo_ai_public_chat_max_tokens' => 'max_tokens',
            'ouinpo_ai_exercise_ai_max_tokens' => 'max_tokens',
            'ouinpo_ai_practical_ai_max_tokens' => 'max_tokens',
            'ouinpo_ai_projects_student_max_tokens' => 'max_tokens',
            'ouinpo_ai_public_rag_max_tokens' => 'max_tokens',
            'ouinpo_ai_disabled_message' => 'text',
            'ouinpo_ai_privacy_notice' => 'long_text',
            'ouinpo_ai_chatbox_welcome_message' => 'long_text',
        ], self::persona_schema(), [
            'ouinpo_ai_rag_system_prompt' => 'long_text',
            'ouinpo_ai_exercise_correction_prompt' => 'long_text',
            'ouinpo_ai_practical_correction_prompt' => 'long_text',
            'ouinpo_ai_suggestions_prompt' => 'long_text',
            'ouinpo_ai_out_of_program_guardrails' => 'long_text',
            'ouinpo_ai_anonymous_default_school_level' => 'key',
            'ouinpo_ai_show_rag_sources' => 'bool',
            'ouinpo_ai_debug_logs' => 'bool',
            'ouinpo_public_exercises_enabled' => 'bool',
            'ouinpo_public_hints_enabled' => 'bool',
            'ouinpo_public_solutions_enabled' => 'bool',
            'ouinpo_public_practical_subjects_enabled' => 'bool',
            'ouinpo_public_practical_files_enabled' => 'bool',
            'ouinpo_public_written_subjects_enabled' => 'bool',
            'ouinpo_public_written_files_enabled' => 'bool',
            'ouinpo_public_written_answer_zones_enabled' => 'bool',
            'ouinpo_public_written_ai_enabled' => 'bool',
            'ouinpo_sf_openai_api_key' => 'secret',
            'ouinpo_sf_model' => 'model',
            'ouinpo_sf_embed_model' => 'model',
            'ouinpo_sf_memory_days' => 'memory_days',
            'ouinpo_sf_members_only' => 'bool',
            'ouinpo_sf_wxr_path' => 'path',
            'ouinpo_sf_albert_enabled' => 'bool',
            'ouinpo_sf_public_albert_enabled' => 'bool',
            'ouinpo_sf_albert_api_key' => 'secret',
            'ouinpo_sf_albert_base_url' => 'url',
            'ouinpo_sf_albert_model' => 'model',
            'ouinpo_sf_albert_code_model' => 'model',
            'ouinpo_sf_albert_embedding_model' => 'model',
            'ouinpo_sf_albert_reranker_model' => 'model',
            'ouinpo_sf_albert_ocr_model' => 'model',
            'ouinpo_sf_rag_embedding_provider' => 'provider',
            'ouinpo_sf_public_hourly_limit' => 'quota',
            'ouinpo_sf_public_daily_limit' => 'quota',
            'ouinpo_sf_rag_rerank_candidates' => 'rerank_candidates',
            'ouinpo_sf_max_embeddings_run' => 'embedding_budget',
            'ouinpo_sf_ai_notice_url' => 'url_or_path',
            'ouinpo_sf_ai_notice_public' => 'long_text',
            'ouinpo_sf_ai_notice_logged' => 'long_text',
        ]);
    }

    public static function sanitize_bool($value): int
    {
        return in_array($value, [1, '1', true, 'true', 'on'], true) ? 1 : 0;
    }

    public static function sanitize_provider($value): string
    {
        $provider = sanitize_key((string) $value);
        return in_array($provider, self::PROVIDERS, true) ? $provider : self::DEFAULT_PROVIDER;
    }

    public static function sanitize_ocr_provider($value): string
    {
        $provider = sanitize_key((string) $value);
        return in_array($provider, ['albert', 'none'], true) ? $provider : 'albert';
    }

    public static function sanitize_url($value): string
    {
        $url = esc_url_raw(trim((string) $value));
        return $url !== '' ? rtrim($url, '/') : '';
    }

    public static function sanitize_url_or_path($value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (preg_match('~^https?://~i', $value)) {
            return esc_url_raw($value);
        }
        return sanitize_text_field($value);
    }

    public static function sanitize_secret($value): string
    {
        return trim(sanitize_text_field((string) $value));
    }

    public static function sanitize_secret_option(string $option, $value): string
    {
        $value = trim(sanitize_text_field((string) $value));
        if ($value === self::SECRET_UNCHANGED_PLACEHOLDER) {
            return trim((string) get_option($option, self::defaults()[$option] ?? ''));
        }

        return $value;
    }

    public static function sanitize_model($value): string
    {
        $value = trim(sanitize_text_field((string) $value));
        return preg_match('/^[A-Za-z0-9._:\/-]{1,120}$/', $value) ? $value : '';
    }

    public static function sanitize_timeout($value): int
    {
        return max(5, min(120, (int) $value));
    }

    public static function sanitize_max_tokens($value): int
    {
        return max(128, min(8000, (int) $value));
    }

    public static function sanitize_temperature($value): float
    {
        return max(0.0, min(2.0, (float) $value));
    }

    public static function sanitize_top_p($value): float
    {
        return max(0.0, min(1.0, (float) $value));
    }

    public static function sanitize_penalty($value): float
    {
        return max(-2.0, min(2.0, (float) $value));
    }

    public static function sanitize_quota($value): int
    {
        return max(0, min(10000, (int) $value));
    }

    public static function sanitize_memory_days($value): int
    {
        return max(0, min(365, (int) $value));
    }

    public static function sanitize_rerank_candidates($value): int
    {
        return max(10, min(80, (int) $value));
    }

    public static function sanitize_embedding_budget($value): int
    {
        return max(10, min(5000, (int) $value));
    }

    public static function sanitize_text($value): string
    {
        return sanitize_text_field((string) $value);
    }

    public static function sanitize_key($value): string
    {
        return substr(sanitize_key((string) $value), 0, 40);
    }

    public static function sanitize_path($value): string
    {
        return sanitize_text_field((string) $value);
    }

    public static function render_user_message_html(string $value): string
    {
        $html = esc_html($value);

        $html = (string) preg_replace('/`([^`]+)`/u', '<code>$1</code>', $html);
        $html = (string) preg_replace('/\*\*(.+?)\*\*/su', '<strong>$1</strong>', $html);
        $html = (string) preg_replace('/__(.+?)__/su', '<strong>$1</strong>', $html);
        $html = (string) preg_replace('/(?<!\*)\*(?!\s)(.+?)(?<!\s)\*(?!\*)/su', '<em>$1</em>', $html);
        $html = (string) preg_replace('/(?<!_)_(?!\s)(.+?)(?<!\s)_(?!_)/su', '<em>$1</em>', $html);

        return wp_kses_post(wpautop($html));
    }

    public static function sanitize_long_text($value): string
    {
        $value = wp_unslash((string) $value);
        $value = wp_kses($value, []);
        return trim($value);
    }

    private static function resolve_persona_option(string $key): string
    {
        $key = trim($key);
        if (str_starts_with($key, 'ouinpo_ai_persona_')) {
            return $key;
        }

        return 'ouinpo_ai_persona_' . sanitize_key($key);
    }

    private static function persona_schema(): array
    {
        $schema = [];
        foreach (array_keys(self::persona_definitions()) as $option) {
            $schema[$option] = 'long_text';
        }
        return $schema;
    }

    private static function persona_defaults(): array
    {
        $defaults = [];
        foreach (self::persona_definitions() as $option => $definition) {
            $defaults[$option] = (string) ($definition['default'] ?? '');
        }
        return $defaults;
    }

    private static function persona_definitions(): array
    {
        return [
            'ouinpo_ai_persona_general' => [
                'label' => 'Persona generale',
                'category' => 'persona',
                'description' => 'Fallback commun quand aucun persona specialise ne s applique.',
                'help' => 'Definit le role, le ton et la posture par defaut de l assistant IA.',
                'default' => self::default_general_persona(),
                'rows' => 4,
            ],
            'ouinpo_ai_persona_chatbox' => [
                'label' => 'Chatbox / SegFault',
                'category' => 'persona',
                'description' => 'Assistant conversationnel du site, RAG et aide generale aux eleves.',
                'help' => 'Exemple : Tu es un assistant pedagogique bienveillant, clair et exigeant, qui aide l eleve a progresser sans donner directement la reponse.',
                'default' => self::default_chatbox_persona(),
                'rows' => 6,
            ],
            'ouinpo_ai_persona_public' => [
                'label' => 'Chat public anonyme',
                'category' => 'persona',
                'description' => 'Assistant visible par les visiteurs anonymes, avec prudence RGPD renforcee.',
                'help' => 'Definit seulement le ton du chat public. Les garde-fous publics restent des consignes internes.',
                'default' => 'Tu aides un visiteur anonyme en NSI/SNT. Reste bref, prudent, sans memoire utilisateur ni donnee personnelle.',
                'rows' => 4,
            ],
            'ouinpo_ai_persona_student' => [
                'label' => 'Eleve connecte',
                'category' => 'persona',
                'description' => 'Aide pedagogique individuelle, sans faire le travail a la place de l eleve.',
                'help' => 'Persona general pour les interactions eleve quand un persona plus specialise ne s applique pas.',
                'default' => 'Tu aides un eleve en NSI/SNT avec bienveillance. Guide par etapes sans faire le travail a sa place.',
                'rows' => 4,
            ],
            'ouinpo_ai_persona_teacher' => [
                'label' => 'Enseignant',
                'category' => 'persona',
                'description' => 'Aide generale a la preparation pedagogique cote enseignant.',
                'help' => 'Persona general pour les usages enseignant quand un persona plus specialise ne s applique pas.',
                'default' => 'Tu aides un enseignant a preparer des pistes pedagogiques sobres, verifiables et adaptees au programme.',
                'rows' => 4,
            ],
            'ouinpo_ai_persona_exercise_correction' => [
                'label' => 'Correction exercices',
                'category' => 'persona',
                'description' => 'Correction des reponses d eleves aux exercices courts.',
                'help' => 'Ne pas mettre ici le schema JSON ni les regles de validation : elles restent dans les consignes internes.',
                'default' => 'Tu es un correcteur pedagogique bienveillant pour des eleves de lycee en NSI/SNT.',
                'rows' => 4,
            ],
            'ouinpo_ai_persona_practical_correction' => [
                'label' => 'Correction sujets pratiques / code',
                'category' => 'persona',
                'description' => 'Correction prudente de code et d appels de sujets pratiques NSI.',
                'help' => 'Definit le ton du correcteur de code. Les regles de JSON, de robustesse et d analyse statique restent internes.',
                'default' => 'Tu es CodeBogue, une IA specialisee dans la correction de code Python pour la specialite NSI.',
                'rows' => 4,
            ],
            'ouinpo_ai_persona_copy_correction' => [
                'label' => 'Correction copies et rendus',
                'category' => 'persona',
                'description' => 'Correction assistee de copies scannees ou de rendus numeriques.',
                'help' => 'Definit la posture de correction. Les regles anti-invention et de validation professeur restent internes.',
                'default' => 'Tu aides un enseignant a corriger une copie ou un rendu numerique. Tu proposes seulement une correction : l enseignant valide.',
                'rows' => 4,
            ],
            'ouinpo_ai_persona_written_subject' => [
                'label' => 'Annales / sujets ecrits',
                'category' => 'persona',
                'description' => 'Analyse et conseils sur les reponses aux sujets ecrits de bac NSI.',
                'help' => 'Definit le ton des conseils sur annales. Les interdictions de note chiffree et de corrige complet restent internes.',
                'default' => 'Tu es un assistant pedagogique NSI pour les sujets ecrits de bac. Tu restes prudent, bienveillant et non exhaustif.',
                'rows' => 4,
            ],
            'ouinpo_ai_persona_assessment_generation' => [
                'label' => 'Generation pedagogique',
                'category' => 'persona',
                'description' => 'Generation d exercices, devoirs, structures d annales et imports pedagogiques.',
                'help' => 'Definit la posture de generation. Les schemas JSON et contraintes de programme restent internes.',
                'default' => 'Tu aides un enseignant NSI a produire des contenus pedagogiques structures, sobres et conformes au programme.',
                'rows' => 4,
            ],
            'ouinpo_ai_persona_projects_teacher' => [
                'label' => 'Projects enseignant',
                'category' => 'persona',
                'description' => 'Suggestions encadrees pour le suivi de projets BTS SIO cote enseignant.',
                'help' => 'Definit le ton de l assistant Projects pour les enseignants.',
                'default' => 'Tu es Assistant pataprojectif, aide encadree pour projets BTS SIO cote enseignant.',
                'rows' => 4,
            ],
            'ouinpo_ai_persona_projects_student' => [
                'label' => 'Projects eleve / portfolio',
                'category' => 'persona',
                'description' => 'Aide a la reflexion personnelle et au portfolio projet cote eleve.',
                'help' => 'Definit le ton d accompagnement portfolio. Les interdictions d inventer des preuves ou traces restent internes.',
                'default' => 'Tu es un assistant BTS SIO pour aider un eleve a preparer son portfolio personnel.',
                'rows' => 4,
            ],
        ];
    }

    private static function default_general_persona(): string
    {
        return 'Tu es un assistant pedagogique pour la NSI et la SNT. Tu expliques sobrement, tu guides par etapes, tu respectes le programme et tu refuses les demandes hors cadre sans inventer de ressource.';
    }

    private static function default_chatbox_persona(): string
    {
        return 'Tu es SegFault, assistant pedagogique NSI/SNT du site OuInPo. Tu es bienveillant, legerement taquin, clair et concis. Tu guides par indices progressifs, tu aides l eleve a comprendre sans faire le travail a sa place, tu priorises les sources et contextes fournis, tu n inventes pas de ressource et tu refuses les hors-sujets avec tact.';
    }

    private static function default_rag_prompt(): string
    {
        return 'Utilise en priorite le contexte RAG fourni. Ne cite que des sources presentes dans le contexte. Si une information manque, dis-le clairement sans inventer.';
    }

    private static function default_exercise_prompt(): string
    {
        return 'Corrige une reponse d\'eleve avec bienveillance. Ne donne pas le corrige complet. Retourne un JSON valide avec verdict, feedback, next_steps, confidence et safe_to_mark_solved.';
    }

    private static function default_practical_prompt(): string
    {
        return 'Corrige un sujet pratique NSI avec prudence. Evalue la logique, les cas limites attendus et la conformite a la consigne. Retourne uniquement un JSON valide.';
    }

    private static function default_guardrails(): string
    {
        return 'Si la question depasse le niveau scolaire indique, signale que la notion n\'est pas exigible, donne seulement une intuition courte et ne propose pas d\'exercice hors programme.';
    }

    private static function secretConstants(): array
    {
        return [
            'ouinpo_ai_api_key' => ['OUINPO_AI_API_KEY', 'OUINPO_ALBERT_API_KEY'],
            'ouinpo_sf_albert_api_key' => ['OUINPO_SF_ALBERT_API_KEY', 'OUINPO_ALBERT_API_KEY', 'OUINPO_AI_API_KEY'],
            'ouinpo_sf_openai_api_key' => ['OUINPO_SF_OPENAI_API_KEY', 'OUINPO_OPENAI_API_KEY'],
        ];
    }
}
