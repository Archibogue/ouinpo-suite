<?php
namespace Ouinpo\Exercises\Rest;

use Ouinpo\Exercises\Services\AiPedagogicalContextService;
use Ouinpo\Suite\Core\Privacy\LearningDataPolicy;
use WP_REST_Request;
use WP_REST_Response;

if (!defined('ABSPATH')) exit;

final class AiAnswerRoutes {

  public static function register(): void {
    register_rest_route('ouinpo/v1', '/exercises/(?P<id>\d+)/ai-evaluate', [
      'methods'  => 'POST',
      'callback' => [__CLASS__, 'evaluate_answer'],
        'permission_callback' => [__CLASS__, 'can_evaluate_ai'],
    ]);
  }

public static function can_evaluate_ai() {
  if (is_user_logged_in()) {
    return true;
  }

  if (!self::public_ai_enabled()) {
    return new \WP_Error(
      'ouinpo_ai_login_required',
      'Connexion requise pour utiliser cette correction IA.',
      ['status' => 401]
    );
  }

  return true;
}

private static function public_ai_enabled(): bool {
  return \Ouinpo\Suite\Core\AiSettings::enabled_for_usage('exercise_correction')
    && \Ouinpo\Suite\Core\AiSettings::public_enabled()
    && class_exists('\\OuInPo\\SegFault\\Albert')
    && \OuInPo\SegFault\Albert::public_available();
}

private static function consume_public_ai_quota() {

  $minute = \Ouinpo\Suite\Core\AiSettings::quota('ouinpo_ai_exercise_ai_per_minute');

  $day = \Ouinpo\Suite\Core\AiSettings::quota('ouinpo_ai_exercise_ai_per_day');

  if (is_user_logged_in()) {

    $teacher_quota = \Ouinpo\Suite\Core\AiSettings::currentUserUsesTeacherAiQuota();

    return \Ouinpo\Suite\Core\AiSettings::consumeUserRateLimit(
      $teacher_quota ? 'teacher_ai' : 'exercise_ai',
      get_current_user_id(),
      $teacher_quota ? \Ouinpo\Suite\Core\AiSettings::quota('ouinpo_ai_teacher_per_minute') : $minute,
      $teacher_quota ? \Ouinpo\Suite\Core\AiSettings::quota('ouinpo_ai_teacher_per_day') : $day
    );

  }



  return \Ouinpo\Suite\Core\AiSettings::consumePublicRateLimit(
    'exercise_ai',
    min($minute, \Ouinpo\Suite\Core\AiSettings::quota('ouinpo_ai_public_ip_per_minute')),
    min($day, \Ouinpo\Suite\Core\AiSettings::quota('ouinpo_ai_public_ip_per_day')),
    \Ouinpo\Suite\Core\AiSettings::quota('ouinpo_ai_public_global_per_day'),
    \Ouinpo\Suite\Core\AiSettings::quota('ouinpo_ai_public_global_per_minute')
  );

}


  public static function evaluate_answer(WP_REST_Request $request): WP_REST_Response {
    global $wpdb;

    $exercise_id = (int) $request['id'];
    $user_id     = (int) get_current_user_id();
    $can_store   = $user_id > 0 && (new LearningDataPolicy())->canStoreLearningData($user_id);
    $store_reason = $user_id > 0 ? 'tracking_disabled' : 'not_logged_in';
    $params      = $request->get_json_params();
    $answer      = trim((string) ($params['answer'] ?? ''));

    if ($exercise_id <= 0) {
      return new WP_REST_Response(['error' => 'bad_exercise_id'], 400);
    }

    if ($answer === '') {
      return new WP_REST_Response([
        'error'   => 'missing_answer',
        'message' => 'Réponse vide.'
      ], 400);
    }

    if (mb_strlen($answer) > 12000) {
      return new WP_REST_Response([
        'error'   => 'answer_too_long',
        'message' => 'Réponse trop longue.'
      ], 400);
    }

    $tExo  = $wpdb->prefix . 'ouin_exo_exercises';
    $tSol  = $wpdb->prefix . 'ouin_exo_solutions';
    $tHint = $wpdb->prefix . 'ouin_exo_hints';
    $tExam = $wpdb->prefix . 'ouin_exo_exam_meta';
    
    $exercise = $wpdb->get_row($wpdb->prepare(
      "SELECT e.id, e.title, e.statement
       FROM {$tExo} e
       LEFT JOIN {$tExam} em ON em.exercise_id = e.id
       WHERE e.id = %d
         AND e.is_active = 1
         AND (em.exam_type IS NULL OR em.exam_type <> 'practical_subject')
       LIMIT 1",
      $exercise_id
    ), ARRAY_A);

    if (!$exercise) {
      return new WP_REST_Response(['error' => 'exercise_not_found'], 404);
    }

    $solutions = $wpdb->get_results($wpdb->prepare(
      "SELECT id, title, content, is_official, solution_order
       FROM $tSol
       WHERE exercise_id = %d
       ORDER BY is_official DESC, solution_order ASC, id ASC",
      $exercise_id
    ), ARRAY_A);

    $hints = $wpdb->get_results($wpdb->prepare(
      "SELECT id, hint_order, content
       FROM $tHint
       WHERE exercise_id = %d
       ORDER BY hint_order ASC, id ASC",
      $exercise_id
    ), ARRAY_A);

    $quota = self::consume_public_ai_quota();
    if (is_wp_error($quota)) {
      return new WP_REST_Response([
        'error'   => $quota->get_error_code(),
        'message' => $quota->get_error_message(),
      ], (int) ($quota->get_error_data()['status'] ?? 429));
    }

    $parts = self::extract_answer_parts($answer);

$syntax = [
  'available' => false,
  'ok'        => null,
  'kind'      => null,
  'line'      => 0,
  'message'   => '',
];
$style_warnings = [];

if ($parts['has_code']) {
  $codes = is_array($parts['codes']) ? $parts['codes'] : [];
  $code_count = count($codes);
  $syntax_available = false;

  foreach ($codes as $index => $code) {
    $check = self::check_python_syntax($code);

    if (!empty($check['available'])) {
      $syntax_available = true;
    }

    if (!empty($check['available']) && empty($check['ok'])) {
      $ai = self::build_syntax_feedback($check, $index + 1, $code_count);

      if ($can_store) {
        self::store_attempt($user_id, $exercise_id, $answer, $ai);
      }

      return new WP_REST_Response([
        'verdict'        => (string) ($ai['verdict'] ?? 'incorrect'),
        'feedback'       => (string) ($ai['feedback'] ?? 'Ton code est à revoir.'),
        'next_steps'     => is_array($ai['next_steps'] ?? null) ? array_values($ai['next_steps']) : [],
        'confidence'     => isset($ai['confidence']) ? (float) $ai['confidence'] : 0.0,
        'style_warnings' => [],
        'safe_to_mark_solved' => false,
        'stored'         => $can_store,
        'reason'         => $can_store ? null : $store_reason,
      ], 200);
    }

    $warnings = self::check_python_style_warnings($code);

    if ($code_count > 1) {
      $warnings = array_map(
        static fn(string $w): string => 'Bloc ' . ($index + 1) . ' — ' . $w,
        $warnings
      );
    }

    $style_warnings = array_merge($style_warnings, $warnings);
  }

  $style_warnings = array_values(array_unique($style_warnings));

  $syntax = [
    'available' => $syntax_available,
    'ok'        => $syntax_available ? true : null,
    'kind'      => null,
    'line'      => 0,
    'message'   => '',
  ];
}

    $ai = self::evaluate_with_ai(
      $exercise,
      $solutions,
      $hints,
      $answer,
      $parts,
      $syntax,
      $style_warnings
    );
    
    if (!empty($ai['technical_error'])) {
      return new WP_REST_Response([
        'error'   => 'ai_unavailable',
        'message' => (string) ($ai['message'] ?? 'Évaluation impossible pour le moment.'),
      ], 503);
    }    

    if ($can_store) {
        self::store_attempt($user_id, $exercise_id, $answer, $ai);
    }

    return new WP_REST_Response([
      'verdict'             => (string) ($ai['verdict'] ?? 'incorrect'),
      'feedback'            => (string) ($ai['feedback'] ?? 'Évaluation impossible pour le moment.'),
      'next_steps'          => is_array($ai['next_steps'] ?? null) ? array_values($ai['next_steps']) : [],
      'confidence'          => isset($ai['confidence']) ? (float) $ai['confidence'] : 0.0,
      'style_warnings'      => array_values($style_warnings),
      'safe_to_mark_solved' => !empty($ai['safe_to_mark_solved']),
      'stored'              => $can_store,
      'reason'              => $can_store ? null : $store_reason,
    ], 200);
  }

  private static function evaluate_with_ai(
    array $exercise,
    array $solutions,
    array $hints,
    string $raw_answer,
    array $parts,
    ?array $syntax,
    array $style_warnings = []
  ): array {
    if (!class_exists('\OuInPo\SegFault\OpenAI')) {
      return [
        'technical_error' => true,
        'message'         => 'Le correcteur IA n’est pas disponible pour le moment.',
      ];
    }

    $statement = self::html_to_text((string) ($exercise['statement'] ?? ''));
    $title     = trim((string) ($exercise['title'] ?? 'Exercice'));

    $solutions_text = [];
    foreach ($solutions as $s) {
      $sol_title = trim((string) ($s['title'] ?? 'Corrigé'));
      $sol_body  = self::html_to_text((string) ($s['content'] ?? ''));
      if ($sol_body === '') {
        continue;
      }

      $flag = self::truthy($s['is_official'] ?? null) ? ' (officielle)' : '';
      $solutions_text[] = "- {$sol_title}{$flag}\n{$sol_body}";
    }

    $hints_text = [];
    foreach ($hints as $h) {
      $order = (int) ($h['hint_order'] ?? 0);
      $body  = self::html_to_text((string) ($h['content'] ?? ''));
      if ($body === '') {
        continue;
      }
      $hints_text[] = "- Indice {$order} : {$body}";
    }

    $code_guidance = $parts['has_code']
      ? "- La réponse peut contenir un ou plusieurs blocs [code]...[/code]. Analyse-les tous.
    - Vérifie d'abord la logique et la conformité à l'énoncé.
    - N'exige jamais une f-string, des commentaires, des noms de variables identiques au corrigé, des tests supplémentaires ou une présentation particulière, sauf si l'énoncé le demande explicitement.
    - De petites différences de style ou de formulation ne suffisent pas à rendre la réponse fausse.
    - Si l'élève a aussi rédigé un texte hors des blocs [code], utilise-le comme explication complémentaire.
    - Les remarques de bon style Python fournies sont non bloquantes."
      : "- La réponse ne contient pas de bloc [code]. Évalue uniquement la réponse rédigée.";

    $persona = \Ouinpo\Suite\Core\AiSettings::persona('exercise_correction', 'ouinpo_ai_persona_teacher');
    $configured_system = \Ouinpo\Suite\Core\AiSettings::prompt('ouinpo_ai_exercise_correction_prompt');
    $pedagogical_context = AiPedagogicalContextService::forExercise((int) ($exercise['id'] ?? 0), [
      'user_id' => is_user_logged_in() ? (int) get_current_user_id() : 0,
    ]);
    $rag_debug = [];
    $course_context = AiPedagogicalContextService::courseContext(
      implode("\n", array_values(array_filter([$title, $statement]))),
      $pedagogical_context,
      4,
      1000,
      $rag_debug
    );
    $program_guardrail = AiPedagogicalContextService::programGuardrail($pedagogical_context);

    \Ouinpo\Suite\Core\AiSettings::debug_log('Exercise correction pedagogical context', array_merge([
      'usage' => 'exercise_correction',
      'exercise_id' => (int) ($exercise['id'] ?? 0),
      'course_context_chars' => strlen($course_context),
      'course_context_count' => (int) ($rag_debug['count'] ?? 0),
      'program_guardrail' => $program_guardrail !== '' ? 1 : 0,
    ], AiPedagogicalContextService::debugMeta($pedagogical_context)));

    $system = <<<TXT
{$persona}

Consigne configurable de correction :
{$configured_system}

Tache metier : evaluer avec bienveillance une reponse d eleve de lycee en NSI/SNT.

Tu évalues une réponse d'élève à partir :
- d'un énoncé,
- de corrigés de référence,
- d'indices éventuels.

Règles :
- Accepte les formulations différentes si le fond est juste.
- Ne pénalise pas une réponse simplement parce qu'elle n'emploie pas les mêmes mots que le corrigé.
- N'invente aucune exigence absente de l'énoncé.
- N'ajoute pas de notion hors programme.
- Ne donne pas le corrigé complet.
- Pour une réponse contenant du code, ne mets safe_to_mark_solved à true que si le code répond clairement à l’énoncé, sans erreur logique visible, avec un comportement suffisamment robuste sur les cas attendus.
- Pour une réponse sans code, safe_to_mark_solved peut être true si la réponse est correcte, précise et suffisante pour l’exercice.
- Si le verdict n’est pas "correct", safe_to_mark_solved doit toujours être false.
- Si le verdict est "correct" et safe_to_mark_solved vaut true, next_steps doit être une liste vide sauf si une action est explicitement demandée par l’énoncé.
- Si la réponse est correcte sur l'essentiel, mets "correct".
- Si elle va dans la bonne direction mais reste incomplète ou imprécise, mets "partial".
- Si elle est fausse, très incomplète ou hors sujet, mets "incorrect".
- Si l’exercice contient plusieurs questions, vérifie que les éléments essentiels sont bien présents.
- Ne pénalise pas un simple ordre différent, une présentation compacte ou une réponse non numérotée si l’association reste compréhensible.
- Ne pénalise jamais l’absence de f-string, de commentaires, de style particulier, de tests supplémentaires ou de noms de variables identiques au corrigé, sauf si l’énoncé l’exige explicitement.
- Mets "partial" seulement s’il manque réellement un élément attendu, si une partie importante est fausse, ou si la réponse est trop floue pour être comprise.
- Le champ "feedback" doit être rédigé en français pour l'élève.
- Le tableau "next_steps" doit contenir 1 à 3 conseils courts, en français, utiles et concrets.
Contexte pedagogique :

- Le bloc CONTEXTE PEDAGOGIQUE STRUCTURE transmis dans le message utilisateur fait autorite pour le niveau attendu, le cycle et l ordre du niveau dans le cycle.

- Les niveaux sont configurables : ne raisonne pas avec des slugs historiques supposes.

- Corrige selon le niveau attendu de la question ou de l exercice. Le niveau de reference sert a adapter le feedback, pas a abaisser les attendus de l enonce.

- Si le garde-fou programme indique un niveau attendu plus avance, ne penalise pas ce decalage quand l enonce demande explicitement la notion.

{$code_guidance}

Réponds UNIQUEMENT avec un JSON valide, sans aucun texte avant ou après, au format exact :
{
  "verdict": "correct|partial|incorrect",
  "feedback": "texte court en français",
  "next_steps": ["conseil 1", "conseil 2"],
  "confidence": 0.84,
  "safe_to_mark_solved": false
}
TXT;

    $user_parts = [
      "TITRE DE L'EXERCICE :",
      $title !== '' ? $title : 'Exercice',

      "ÉNONCÉ :",
      $statement !== '' ? $statement : '(énoncé vide)',

      "CORRIGÉS DE RÉFÉRENCE :",
      $solutions_text ? implode("\n\n", $solutions_text) : '(aucun corrigé)',

      "INDICES DISPONIBLES :",
      $hints_text ? implode("\n", $hints_text) : '(aucun indice)',
    ];

    $user_parts[] = "CONTEXTE PEDAGOGIQUE STRUCTURE :";
    $user_parts[] = AiPedagogicalContextService::promptBlock($pedagogical_context);

    if ($course_context !== '') {
      $user_parts[] = "EXTRAITS DE PROGRAMME / COURS RAG :";
      $user_parts[] = $course_context;
    }

    if ($program_guardrail !== '') {
      $user_parts[] = "GARDE-FOU PROGRAMME :";
      $user_parts[] = $program_guardrail;
    }

    if ($parts['has_text']) {
      $user_parts[] = "RÉPONSE RÉDIGÉE DE L'ÉLÈVE :";
      $user_parts[] = $parts['text'];
    }

    if ($parts['has_code']) {
      $user_parts[] = "BLOCS DE CODE FOURNIS PAR L'ÉLÈVE :";
    
      foreach ($parts['codes'] as $index => $code) {
        $user_parts[] = "BLOC DE CODE " . ($index + 1) . " :\n" . $code;
      }

      if (!empty($syntax['available']) && !empty($syntax['ok'])) {
        $user_parts[] = "CONTRÔLE TECHNIQUE :";
        $user_parts[] = "Tous les blocs de code fournis sont syntaxiquement valides en Python.";
      } elseif (empty($syntax['available'])) {
        $user_parts[] = "CONTRÔLE TECHNIQUE :";
        $user_parts[] = "Aucune vérification automatique de syntaxe n'a pu être effectuée sur le serveur.";
      }

      if (!empty($style_warnings)) {
        $user_parts[] = "REMARQUES DE BON STYLE PYTHON (NON BLOQUANTES) :";
        $user_parts[] = implode("\n", array_map(
          static fn(string $w): string => '- ' . $w,
          $style_warnings
        ));
      }
    }

    if (!$parts['has_text'] && !$parts['has_code']) {
      $user_parts[] = "RÉPONSE DE L'ÉLÈVE :";
      $user_parts[] = $raw_answer;
    }

    try {
    $options = [
      'temperature' => (float) get_option('ouinpo_ai_temperature', 0.0),
      'max_tokens'  => \Ouinpo\Suite\Core\AiSettings::maxTokens('ouinpo_ai_exercise_ai_max_tokens'),
      'response_format' => [
        'type' => 'json_object',
      ],
    ];
    
    if (!empty($parts['has_code'])) {
      $options['albert_purpose'] = 'code';
    }
    
    $raw = \OuInPo\SegFault\OpenAI::respond([
      ['role' => 'system', 'content' => $system],
      ['role' => 'user',   'content' => implode("\n\n", $user_parts)],
    ], $options);
    } catch (\Throwable $e) {
      \Ouinpo\Suite\Core\AiSettings::debug_log('Exercise correction AI error', ['usage' => 'exercise_correction', 'error' => $e->getMessage()]);
    return [
      'technical_error' => true,
      'message'         => 'Le correcteur IA a rencontré une erreur.',
    ];
    }

    $parsed = self::extract_json($raw);
    if (!is_array($parsed)) {
      \Ouinpo\Suite\Core\AiSettings::debug_log('Exercise correction invalid JSON', ['usage' => 'exercise_correction']);
      return [
        'technical_error' => true,
        'message'         => 'Je n’ai pas réussi à analyser correctement ta réponse cette fois-ci.',
      ];
    }

    $verdict = (string) ($parsed['verdict'] ?? 'incorrect');
    if (!in_array($verdict, ['correct', 'partial', 'incorrect'], true)) {
      $verdict = 'incorrect';
    }

    $feedback = trim((string) ($parsed['feedback'] ?? ''));
    if ($feedback === '') {
      $feedback = ($verdict === 'correct')
        ? 'Ta réponse est correcte.'
        : (($verdict === 'partial')
          ? 'Ta réponse va dans la bonne direction, mais elle reste incomplète.'
          : 'Ta réponse est à revoir.');
    }

    $next_steps = [];
    if (!empty($parsed['next_steps']) && is_array($parsed['next_steps'])) {
      foreach ($parsed['next_steps'] as $step) {
        $step = trim((string) $step);
        if ($step !== '') {
          $next_steps[] = $step;
        }
      }
    }
    $next_steps = array_values(array_slice($next_steps, 0, 3));

    if (!$next_steps && $verdict !== 'correct') {
      $next_steps[] = 'Relis la consigne et compare ta réponse avec ce qui est demandé.';
    }

    $confidence = isset($parsed['confidence']) ? (float) $parsed['confidence'] : 0.0;
    $confidence = max(0.0, min(1.0, $confidence));
    
    $safe_to_mark_solved = !empty($parsed['safe_to_mark_solved']);
    
    if ($verdict !== 'correct') {
      $safe_to_mark_solved = false;
    }
    
    /*
     * Si l’exercice est officiellement validable, on évite les conseils génériques
     * qui donnent l’impression que la réponse n’est pas vraiment validée.
     */
    if ($verdict === 'correct' && $safe_to_mark_solved) {
      $next_steps = [];
    }
    
    return [
      'verdict'             => $verdict,
      'feedback'            => $feedback,
      'next_steps'          => $next_steps,
      'confidence'          => $confidence,
      'safe_to_mark_solved' => $safe_to_mark_solved,
    ];
  }

  private static function extract_answer_parts(string $raw): array {
    $raw = trim($raw);

    preg_match_all('/\[code\](.*?)\[\/code\]/is', $raw, $matches);

    $codes = [];
    if (!empty($matches[1])) {
      foreach ($matches[1] as $block) {
        $code = trim((string) $block);
        if ($code !== '') {
          $codes[] = $code;
        }
      }
    }

    $text = preg_replace('/\[code\].*?\[\/code\]/is', '', $raw);
    $text = trim((string) $text);

    return [
      'raw'          => $raw,
      'text'         => $text,
      'codes'        => $codes,
      'primary_code' => $codes ? $codes[0] : '',
      'has_text'     => ($text !== ''),
      'has_code'     => !empty($codes),
    ];
  }

  private static function build_syntax_feedback(array $syntax, int $block_index = 1, int $block_count = 1): array {
    $kind = (string) ($syntax['kind'] ?? 'syntax');
    $line = (int) ($syntax['line'] ?? 0);
    $msg  = trim((string) ($syntax['message'] ?? ''));

    $line_text = $line > 0 ? ' ligne ' . $line : '';
    $detail    = $msg !== '' ? ' : ' . $msg : '';
    $block_text = $block_count > 1 ? ' dans le bloc de code ' . $block_index : '';

    if ($kind === 'indentation') {
        return [
          'verdict'             => 'incorrect',
          'feedback'            => 'Ton code a un problème d’indentation' . $block_text . $line_text . $detail . '.',
          'next_steps'          => [
            'Vérifie les blocs après les lignes qui se terminent par un deux-points.',
            'Aligne les instructions d’un même bloc au même niveau.',
            'Corrige l’indentation puis renvoie ton code.'
          ],
          'confidence'          => 0.98,
          'safe_to_mark_solved' => false,
        ];
    }

        return [
          'verdict'             => 'incorrect',
          'feedback'            => 'Ton code contient une erreur de syntaxe' . $block_text . $line_text . $detail . '.',
          'next_steps'          => [
            'Relis attentivement la ligne indiquée.',
            'Vérifie les parenthèses, les deux-points et l’écriture des mots-clés Python.',
            'Corrige puis soumets à nouveau ta réponse.'
          ],
          'confidence'          => 0.98,
          'safe_to_mark_solved' => false,
        ];
  }

  private static function check_python_syntax(string $code): array {
    $code = trim($code);
    if ($code === '') {
      return ['available' => false, 'ok' => null, 'kind' => null, 'line' => 0, 'message' => ''];
    }

    if (!function_exists('proc_open')) {
      return ['available' => false, 'ok' => null, 'kind' => null, 'line' => 0, 'message' => ''];
    }

    $python = self::find_python_command();
    if ($python === null) {
      return ['available' => false, 'ok' => null, 'kind' => null, 'line' => 0, 'message' => ''];
    }

    $script = <<<'PY'
import ast
import sys

code = sys.stdin.read()

try:
    ast.parse(code)
    print("OK")
except IndentationError as e:
    msg = (e.msg or "").replace("\n", " ").strip()
    print(f"ERR|indentation|{e.lineno or 0}|{msg}")
except SyntaxError as e:
    msg = (e.msg or "").replace("\n", " ").strip()
    print(f"ERR|syntax|{e.lineno or 0}|{msg}")
except Exception as e:
    msg = str(e).replace("\n", " ").strip()
    print(f"ERR|other|0|{msg}")
PY;

    $cmd = escapeshellcmd($python) . ' -c ' . escapeshellarg($script);
    $descriptors = [
      0 => ['pipe', 'r'],
      1 => ['pipe', 'w'],
      2 => ['pipe', 'w'],
    ];

    $process = @proc_open($cmd, $descriptors, $pipes);
    if (!is_resource($process)) {
      return ['available' => false, 'ok' => null, 'kind' => null, 'line' => 0, 'message' => ''];
    }

    fwrite($pipes[0], $code);
    fclose($pipes[0]);

    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);

    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exit = proc_close($process);
    $out  = trim((string) $stdout);

    if ($out === 'OK') {
      return ['available' => true, 'ok' => true, 'kind' => null, 'line' => 0, 'message' => ''];
    }

    if (strpos($out, 'ERR|') === 0) {
      $parts = explode('|', $out, 4);
      return [
        'available' => true,
        'ok'        => false,
        'kind'      => (string) ($parts[1] ?? 'syntax'),
        'line'      => (int) ($parts[2] ?? 0),
        'message'   => trim((string) ($parts[3] ?? '')),
      ];
    }

    if ($exit !== 0 || trim((string) $stderr) !== '') {
      return ['available' => false, 'ok' => null, 'kind' => null, 'line' => 0, 'message' => ''];
    }

    return ['available' => false, 'ok' => null, 'kind' => null, 'line' => 0, 'message' => ''];
  }

  private static function check_python_style_warnings(string $code): array {
    $warnings = [];
    $lines = preg_split("/\r\n|\r|\n/", $code);

    foreach ($lines as $i => $line) {
      $n = $i + 1;

      if (trim($line) === '') {
        continue;
      }

      if (strpos($line, "\t") !== false) {
        $warnings[] = "Ligne {$n} : utilise des espaces plutôt que des tabulations pour l'indentation.";
      }

      if (preg_match('/^( +)/', $line, $m)) {
        $spaces = strlen($m[1]);
        if ($spaces > 0 && $spaces % 4 !== 0) {
          $warnings[] = "Ligne {$n} : l'indentation devrait idéalement utiliser des multiples de 4 espaces.";
        }
      }

      if (preg_match('/\s+$/', $line)) {
        $warnings[] = "Ligne {$n} : évite les espaces inutiles en fin de ligne.";
      }

      if (preg_match('/,[^\s\]\)\}]/', $line)) {
        $warnings[] = "Ligne {$n} : pense à mettre un espace après une virgule.";
      }

      if (preg_match('/[^\s=!<>]=[^=\s]/', $line)) {
        $warnings[] = "Ligne {$n} : ajoute des espaces autour de l'opérateur = pour améliorer la lisibilité.";
      }

      if (preg_match('/\S[+\-*\/%]\S/', $line) && !preg_match('/\*\*/', $line)) {
        $warnings[] = "Ligne {$n} : ajoute des espaces autour des opérateurs pour améliorer la lisibilité.";
      }

      if (mb_strlen($line) > 100) {
        $warnings[] = "Ligne {$n} : la ligne est très longue ; essaie de la raccourcir pour la rendre plus lisible.";
      }
    }

    return array_values(array_unique($warnings));
  }

  private static function find_python_command(): ?string {
    foreach (['python3', 'python'] as $candidate) {
      $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
      ];

      $process = @proc_open(
        escapeshellcmd($candidate) . ' --version',
        $descriptors,
        $pipes
      );

      if (!is_resource($process)) {
        continue;
      }

      fclose($pipes[0]);
      $stdout = stream_get_contents($pipes[1]);
      fclose($pipes[1]);
      $stderr = stream_get_contents($pipes[2]);
      fclose($pipes[2]);

      $exit = proc_close($process);

      if ($exit === 0 && (trim($stdout) !== '' || trim($stderr) !== '')) {
        return $candidate;
      }
    }

    return null;
  }

  private static function extract_json(string $raw): ?array {
    $raw = trim($raw);
    if ($raw === '') {
      return null;
    }

    $raw = preg_replace('/^```(?:json)?\s*/i', '', $raw);
    $raw = preg_replace('/\s*```$/', '', $raw);
    $raw = trim((string) $raw);

    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
      return $decoded;
    }

    if (preg_match('/\{[\s\S]*\}/', $raw, $m)) {
      $decoded = json_decode($m[0], true);
      if (is_array($decoded)) {
        return $decoded;
      }
    }

    return null;
  }

  private static function html_to_text(string $html): string {
    if ($html === '') {
      return '';
    }

    $text = preg_replace('~<(?:br\s*/?|/p|/li|/div|/h[1-6]|/pre)>~i', "\n", $html);
    $text = html_entity_decode(wp_strip_all_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace("/\r\n?/", "\n", $text);
    $text = preg_replace("/\n{3,}/", "\n\n", $text);

    return trim((string) $text);
  }

  private static function truthy($value): bool {
    return in_array($value, [1, '1', true, 'true'], true);
  }

  private static function store_attempt(int $user_id, int $exercise_id, string $answer, array $ai): void {
    global $wpdb;

    $table = $wpdb->prefix . 'ouin_exo_ai_attempts';

    $data = [
      'user_id'     => $user_id,
      'exercise_id' => $exercise_id,
      'answer_text' => $answer,
      'verdict'     => (string) ($ai['verdict'] ?? 'incorrect'),
      'feedback'    => (string) ($ai['feedback'] ?? ''),
      'created_at'  => current_time('mysql'),
    ];
    $formats = ['%d', '%d', '%s', '%s', '%s', '%s'];

    if (isset($ai['confidence']) && $ai['confidence'] !== null) {
      $data['confidence'] = (float) $ai['confidence'];
      $formats[] = '%f';
    }

    $wpdb->insert($table, $data, $formats);
  }
}
