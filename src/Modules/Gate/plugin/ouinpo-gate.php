<?php
// Module interne OuInPo Suite : Gate.

if (!defined('ABSPATH')) {
    exit;
}

/* ================= Helpers ================= */
function ouinpo_gate_salt(){ return 'ouinpo_salt_2025_change_me'; } // ⚠️ à personnaliser

function ouinpo_norm($s){
  $s = trim((string)$s);
  // guillemets typographiques & espaces Unicode → ASCII
  $map = [
    "\xC2\xA0"=>' ', "\xE2\x80\x89"=>' ', "\xE2\x80\x8A"=>' ', "\xE2\x80\xAF"=>' ',
    "‘"=>"'", "’"=>"'", "‚"=>"'", "‛"=>"'", "“"=>'"', "”"=>'"', "„"=>'"', "‟"=>'"'
  ];
  $s = strtr($s, $map);
  // 🔧 enlève tout antislash (\' etc.)
  $s = str_replace('\\', '', $s);
  // unifier sur l'apostrophe simple
  $s = str_replace('"', "'", $s);
  // supprimer TOUS les espaces (Unicode compris)
  $s = preg_replace('~[\s\p{Z}]+~u', '', $s);
  // retirer ; final
  $s = rtrim($s, ';');
  // casse insensible
  return mb_strtolower($s, 'UTF-8');
}
function ouinpo_hash($s){ return hash('sha256', ouinpo_gate_salt().ouinpo_norm($s)); }

function ouinpo_gate_enqueue_assets(): void {
  $rel = 'assets/css/front/gate.css';
  $root_file = dirname(__DIR__, 4) . '/ouinpo-suite.php';
  $base_url = defined('OUINPO_SUITE_URL') ? OUINPO_SUITE_URL : plugin_dir_url($root_file);
  $base_dir = defined('OUINPO_SUITE_DIR') ? OUINPO_SUITE_DIR : dirname(__DIR__, 4) . '/';
  $file = $base_dir . $rel;
  $version = defined('OUINPO_SUITE_VERSION') ? OUINPO_SUITE_VERSION : '1.0.0';

  if (file_exists($file)) {
    $version = (string) filemtime($file);
  }

  wp_enqueue_style('ouinpo-gate-css', $base_url . $rel, [], $version);
}

function ouinpo_gate_enqueue_signpad_script(): void {
  $rel = 'assets/js/front/gate.js';
  $root_file = dirname(__DIR__, 4) . '/ouinpo-suite.php';
  $base_url = defined('OUINPO_SUITE_URL') ? OUINPO_SUITE_URL : plugin_dir_url($root_file);
  $base_dir = defined('OUINPO_SUITE_DIR') ? OUINPO_SUITE_DIR : dirname(__DIR__, 4) . '/';
  $file = $base_dir . $rel;
  $version = defined('OUINPO_SUITE_VERSION') ? OUINPO_SUITE_VERSION : '1.0.0';

  if (file_exists($file)) {
    $version = (string) filemtime($file);
  }

  wp_enqueue_script('ouinpo-gate-js', $base_url . $rel, [], $version, true);
}

/* ================= Corpus des 42 énigmes ================= */
function ouinpo_enigmes(){
  return [
    /* ==== NIVEAU SNT / INTRO PYTHON ==== */
    [ 'theme'=>'Bases numériques', 'prompt'=>"Combien vaut 0b1010 en base 10 ?", 'canon'=>"10", 'note'=>1 ],
    [ 'theme'=>'Booléens', 'prompt'=>"Python : True and False renvoie ?", 'canon'=>"false", 'note'=>2 ],
    [ 'theme'=>'Chaînes', 'prompt'=>"Python : len('OuInPo') renvoie ?", 'canon'=>"6", 'note'=>3 ],
    [ 'theme'=>'Variables', 'prompt'=>"Nom donné à une valeur mémorisée ?", 'canon'=>"variable", 'note'=>4 ],
    [ 'theme'=>'Entrée / Sortie', 'prompt'=>"Python : fonction pour afficher du texte ?", 'canon'=>"print", 'note'=>5 ],
    [ 'theme'=>'Listes', 'prompt'=>"Python : [1,2,3][0] renvoie ?", 'canon'=>"1", 'note'=>6 ],
    [ 'theme'=>'OuInPo', 'prompt'=>"Quel mot magique fait planter un programme tout en lui donnant du sens ? C'est aussi le nom d'un chat", 'canon'=>"segfault", 'note'=>7 ],

    /* ==== NIVEAU PREMIÈRE — STRUCTURES, CONDITIONS, BOUCLES ==== */
    [ 'theme'=>'Conditions', 'prompt'=>"Mot-clé Python pour une alternative ?", 'canon'=>"else", 'note'=>8 ],
    [ 'theme'=>'Boucles', 'prompt'=>"Mot-clé Python pour répéter un bloc tant qu’une condition est vraie ?", 'canon'=>"while", 'note'=>9 ],
    [ 'theme'=>'Listes', 'prompt'=>"Python : [2,3]*2 renvoie ?", 'canon'=>"[2, 3, 2, 3]", 'note'=>10 ],
    [ 'theme'=>'Dictionnaires', 'prompt'=>"Python : {'a':1}['a'] renvoie ?", 'canon'=>"1", 'note'=>11 ],
    [ 'theme'=>'Compréhensions', 'prompt'=>"[x*2 for x in [1,2,3]] renvoie ?", 'canon'=>"[2, 4, 6]", 'note'=>12 ],
    [ 'theme'=>'Fonctions', 'prompt'=>"Mot-clé Python pour définir une fonction ?", 'canon'=>"def", 'note'=>13 ],
    [ 'theme'=>'OuInPo', 'prompt'=>"Lorsque le code propose des solutions imaginaires, comment s’appelle cette science ?", 'canon'=>"'pataphysique", 'note'=>14 ],

    /* ==== ALGORITHMIQUE / STRUCTURES DE DONNÉES ==== */
    [ 'theme'=>'Algorithmes', 'prompt'=>"Nom de l’algorithme qui cherche un élément en parcourant la liste entière ?", 'canon'=>"recherche séquentielle", 'note'=>15 ],
    [ 'theme'=>'Tri', 'prompt'=>"Complexité du tri par insertion au pire ?", 'canon'=>"o(n^2)", 'note'=>16 ],
    [ 'theme'=>'Pile (LIFO)', 'prompt'=>"Après empiler 1, empiler 2, dépiler → renvoie ?", 'canon'=>"2", 'note'=>17 ],
    [ 'theme'=>'File (FIFO)', 'prompt'=>"Après enfiler A, enfiler B, défiler → renvoie ?", 'canon'=>"a", 'note'=>18 ],
    [ 'theme'=>'POO - base', 'prompt'=>"Python : mot-clé pour créer une classe ?", 'canon'=>"class", 'note'=>19 ],
    [ 'theme'=>'POO - init', 'prompt'=>"Nom de la méthode d’initialisation d’un objet Python ?", 'canon'=>"__init__", 'note'=>20 ],
    [ 'theme'=>'OuInPo', 'prompt'=>"Finir cette sentence : La logique n'empêche ...", 'canon'=>"ni la joie, ni le jeu", 'note'=>21 ],

    /* ==== BASES DE DONNÉES / SQL ==== */
    [ 'theme'=>'SQL', 'prompt'=>"Commande SQL pour extraire des données ?", 'canon'=>"select", 'note'=>22 ],
    [ 'theme'=>'SQL WHERE', 'prompt'=>"Mot-clé SQL pour filtrer ?", 'canon'=>"where", 'note'=>23 ],
    [ 'theme'=>'SQL COUNT', 'prompt'=>"SQL : compter les lignes ?", 'canon'=>"count", 'note'=>24 ],
    [ 'theme'=>'SQL tri', 'prompt'=>"Mot-clés SQL pour trier le résultat ?", 'canon'=>"order by", 'note'=>25 ],
    [ 'theme'=>'SQL clé primaire', 'prompt'=>"Propriété d’une clé primaire : unique et… ?", 'canon'=>"non nulle", 'note'=>26 ],
    [ 'theme'=>'SQL clé étrangère', 'prompt'=>"Elle relie une table à une autre, c’est la clé… ?", 'canon'=>"étrangère", 'note'=>27 ],
    [ 'theme'=>'OuInPo', 'prompt'=>"Quel mot Python provoque souvent la révélation du néant ?", 'canon'=>"None", 'note'=>28 ],

    /* ==== TERMINALE — STRUCTURES COMPLEXES / RÉSEAUX ==== */
    [ 'theme'=>'Graphes', 'prompt'=>"Nom du parcours en largeur ?", 'canon'=>"bfs", 'note'=>29 ],
    [ 'theme'=>'Arbres binaires', 'prompt'=>"Parcours infixe d’un ABR : ordre des clés ?", 'canon'=>"croissant", 'note'=>30 ],
    [ 'theme'=>'Réseaux', 'prompt'=>"Combien d’hôtes utilisables dans un /24 ?", 'canon'=>"254", 'note'=>31 ],
    [ 'theme'=>'Encodage', 'prompt'=>"ASCII : ord('A') vaut ?", 'canon'=>"65", 'note'=>32 ],
    [ 'theme'=>'Algorithmes', 'prompt'=>"Nom de l’algorithme de tri O(n log n) stable ?", 'canon'=>"fusion", 'note'=>33 ],
    [ 'theme'=>'Fichiers', 'prompt'=>"Python : mot-clé pour ouvrir un fichier ?", 'canon'=>"open", 'note'=>34 ],
    [ 'theme'=>'OuInPo', 'prompt'=>"Si ton code devient infini, dans quel univers entre-t-il ?", 'canon'=>"boucle divine", 'note'=>35 ],

    /* ==== TERMINALE AVANCÉE / FIN DE CURSUS ==== */
    [ 'theme'=>'POO - égalité', 'prompt'=>"Méthode spéciale Python pour == ?", 'canon'=>"__eq__", 'note'=>36 ],
    [ 'theme'=>'POO - représentation', 'prompt'=>"Méthode spéciale pour affichage lisible d’un objet ?", 'canon'=>"__repr__", 'note'=>37 ],
    [ 'theme'=>'SQL - jointure', 'prompt'=>"Mot-clé pour combiner plusieurs tables ?", 'canon'=>"join", 'note'=>38 ],
    [ 'theme'=>'Complexité', 'prompt'=>"Quel est le nom de l'algorithme qui permet de trouver le plus court chemin entre un noeud de départ et tous les autres noeuds ?", 'canon'=>"Dijkstra", 'note'=>39 ],
    [ 'theme'=>'Réseaux', 'prompt'=>"Protocole d’envoi de mail ?", 'canon'=>"smtp", 'note'=>40 ],
    [ 'theme'=>'POO - héritage', 'prompt'=>"Mot-clé Python pour hériter d’une classe ?", 'canon'=>"super", 'note'=>41 ],
    [ 'theme'=>'OuInPo', 'prompt'=>"Quel laboratoire virtuel se consacre à la Poétique du Code et à la Science de l'Inutile ?", 'canon'=>"ouinpo", 'note'=>42 ],
  ];
}


/* ================= Aliases & Hashes ================= */
function ouinpo_aliases(){
  return [
    5 => [ // index 5 => 6e énigme
      "t1 = tache(1, 'init', 15)",
      "t1=tache(1,'init',15)",
      "t1 = tache(1, \"init\", 15)",
      "t1=tache(1,\"init\",15)",
      "t1 = Tache(1, 'Init', 15)",
      "t1=Tache(1,'Init',15)",
      "t1 = Tache(1, \"Init\", 15)",
      "t1=Tache(1,\"Init\",15)",
      "t1=Tache(1, 'Init', 15)",
      "t1 =Tache(1,'Init',15)",
      "t1= Tache(1,'Init',15)",
      "t1 = Tache(1, 'Init', 15);",
      "t1=Tache(1,'Init',15);",
    ],
  ];
}
function ouinpo_hashes(){
  $HS = [];
  foreach(ouinpo_gate_questions(true) as $i=>$question){
    $HS[$i] = array_map('ouinpo_hash', ouinpo_gate_variants($question));
  }
  return $HS;
}

/* ================= Configuration Gate ================= */
function ouinpo_gate_default_ai_prompt(): string {
  return "Tu es un correcteur pedagogique strict mais bienveillant pour un jeu d'enigmes informatique. Tu dois decider si la reponse de l'eleve correspond a la reponse attendue, meme si elle contient de petites variations de formulation, d'accents, de casse ou d'espaces. Tu ne dois pas reveler la reponse attendue si la reponse est fausse. Tu dois repondre uniquement en JSON valide selon le schema demande.";
}

function ouinpo_gate_default_settings(): array {
  return [
    'enabled' => 1,
    'ai_validation_enabled' => 0,
    'ai_provider' => 'inherit',
    'ai_model' => '',
    'temperature' => 0.0,
    'timeout' => 20,
    'global_cooldown' => 10,
    'global_max_attempts' => 0,
    'fallback_exact_enabled' => 1,
    'ai_unavailable_message' => "La validation automatique est indisponible pour le moment. Reessaie plus tard.",
    'system_prompt' => ouinpo_gate_default_ai_prompt(),
    'debug_logs' => 0,
  ];
}

function ouinpo_gate_school_level_for_note(int $note): string {
  if ($note <= 14) {
    return $note <= 7 ? 'seconde' : 'premiere';
  }
  return $note <= 28 ? 'premiere' : 'terminale';
}

function ouinpo_gate_default_questions(): array {
  $questions = [];
  $aliases = ouinpo_aliases();

  foreach (ouinpo_enigmes() as $index => $legacy) {
    $note = (int)($legacy['note'] ?? ($index + 1));
    $questions[] = [
      'id' => sprintf('gate-%03d', $note),
      'order' => $note,
      'enabled' => 1,
      'title' => (string)($legacy['theme'] ?? ('Enigme ' . $note)),
      'prompt' => (string)($legacy['prompt'] ?? ''),
      'help' => '',
      'expected_answer' => (string)($legacy['canon'] ?? ''),
      'variants' => isset($aliases[$index]) ? implode("\n", array_map('strval', $aliases[$index])) : '',
      'ai_criteria' => "Accepter les formulations equivalentes a la reponse de reference sans reveler cette reponse en cas d'echec.",
      'school_level' => ouinpo_gate_school_level_for_note($note),
      'domain' => (string)($legacy['theme'] ?? ''),
      'success_message' => 'Reponse acceptee.',
      'failure_message' => "Ce n'est pas encore ca. Relis l'enonce et precise ta reponse.",
      'max_attempts' => 0,
      'cooldown' => 0,
      'ai_enabled' => 1,
      'fallback_exact' => 1,
    ];
  }

  return $questions;
}

function ouinpo_gate_sanitize_bool($value): int {
  return in_array($value, [1, '1', true, 'true', 'on', 'yes'], true) ? 1 : 0;
}

function ouinpo_gate_sanitize_provider($value): string {
  $provider = sanitize_key((string)$value);
  return in_array($provider, ['inherit', 'albert', 'openai'], true) ? $provider : 'inherit';
}

function ouinpo_gate_sanitize_model($value): string {
  $model = trim(sanitize_text_field((string)$value));
  return preg_match('/^[A-Za-z0-9._:\/-]{0,120}$/', $model) ? $model : '';
}

function ouinpo_gate_sanitize_long_text($value): string {
  $value = wp_unslash((string)$value);
  return trim(wp_kses($value, []));
}

function ouinpo_gate_sanitize_settings(array $raw): array {
  $defaults = ouinpo_gate_default_settings();
  $raw = array_merge($defaults, $raw);

  return [
    'enabled' => ouinpo_gate_sanitize_bool($raw['enabled'] ?? 0),
    'ai_validation_enabled' => ouinpo_gate_sanitize_bool($raw['ai_validation_enabled'] ?? 0),
    'ai_provider' => ouinpo_gate_sanitize_provider($raw['ai_provider'] ?? 'inherit'),
    'ai_model' => ouinpo_gate_sanitize_model($raw['ai_model'] ?? ''),
    'temperature' => max(0.0, min(2.0, (float)($raw['temperature'] ?? 0))),
    'timeout' => max(5, min(120, (int)($raw['timeout'] ?? 20))),
    'global_cooldown' => max(0, min(3600, (int)($raw['global_cooldown'] ?? 10))),
    'global_max_attempts' => max(0, min(100, (int)($raw['global_max_attempts'] ?? 0))),
    'fallback_exact_enabled' => ouinpo_gate_sanitize_bool($raw['fallback_exact_enabled'] ?? 0),
    'ai_unavailable_message' => sanitize_text_field((string)($raw['ai_unavailable_message'] ?? $defaults['ai_unavailable_message'])),
    'system_prompt' => ouinpo_gate_sanitize_long_text($raw['system_prompt'] ?? $defaults['system_prompt']),
    'debug_logs' => ouinpo_gate_sanitize_bool($raw['debug_logs'] ?? 0),
  ];
}

function ouinpo_gate_sanitize_question(array $raw, int $fallback_order = 0): ?array {
  $id = sanitize_key((string)($raw['id'] ?? ''));
  if ($id === '') {
    $id = 'gate-' . wp_generate_password(8, false, false);
  }

  $levels = ['seconde', 'premiere', 'terminale', 'transversal'];
  $level = sanitize_key((string)($raw['school_level'] ?? 'transversal'));
  if (!in_array($level, $levels, true)) {
    $level = 'transversal';
  }

  $prompt = ouinpo_gate_sanitize_long_text($raw['prompt'] ?? '');
  $expected = ouinpo_gate_sanitize_long_text($raw['expected_answer'] ?? '');
  if ($prompt === '' && $expected === '') {
    return null;
  }

  return [
    'id' => $id,
    'order' => max(0, min(9999, (int)($raw['order'] ?? $fallback_order))),
    'enabled' => ouinpo_gate_sanitize_bool($raw['enabled'] ?? 0),
    'title' => sanitize_text_field((string)($raw['title'] ?? '')),
    'prompt' => $prompt,
    'help' => ouinpo_gate_sanitize_long_text($raw['help'] ?? ''),
    'expected_answer' => $expected,
    'variants' => ouinpo_gate_sanitize_long_text($raw['variants'] ?? ''),
    'ai_criteria' => ouinpo_gate_sanitize_long_text($raw['ai_criteria'] ?? ''),
    'school_level' => $level,
    'domain' => sanitize_text_field((string)($raw['domain'] ?? '')),
    'success_message' => sanitize_text_field((string)($raw['success_message'] ?? 'Reponse acceptee.')),
    'failure_message' => sanitize_text_field((string)($raw['failure_message'] ?? "Ce n'est pas encore ca.")),
    'max_attempts' => max(0, min(100, (int)($raw['max_attempts'] ?? 0))),
    'cooldown' => max(0, min(3600, (int)($raw['cooldown'] ?? 0))),
    'ai_enabled' => ouinpo_gate_sanitize_bool($raw['ai_enabled'] ?? 0),
    'fallback_exact' => ouinpo_gate_sanitize_bool($raw['fallback_exact'] ?? 0),
  ];
}

function ouinpo_gate_sanitize_questions($raw): array {
  $questions = [];
  $raw = is_array($raw) ? $raw : [];

  foreach (array_values($raw) as $index => $question) {
    if (!is_array($question)) {
      continue;
    }
    if (!empty($question['_delete'])) {
      continue;
    }
    $sanitized = ouinpo_gate_sanitize_question($question, $index + 1);
    if ($sanitized !== null) {
      $questions[] = $sanitized;
    }
  }

  usort($questions, static fn($a, $b) => ($a['order'] <=> $b['order']) ?: strcmp($a['id'], $b['id']));
  return $questions;
}

function ouinpo_gate_ensure_config(): void {
  // Migration douce : on initialise depuis l'ancien corpus uniquement si aucune configuration Gate n'existe.
  if (get_option('ouinpo_gate_questions', null) === null) {
    add_option('ouinpo_gate_questions', ouinpo_gate_default_questions(), '', false);
  }
  if (get_option('ouinpo_gate_settings', null) === null) {
    add_option('ouinpo_gate_settings', ouinpo_gate_default_settings(), '', false);
  }
  if (get_option('ouinpo_gate_schema_version', null) === null) {
    add_option('ouinpo_gate_schema_version', '1.0.0', '', false);
  }
}
add_action('init', 'ouinpo_gate_ensure_config', 5);

add_action('admin_init', function(): void {
  register_setting('ouinpo_gate', 'ouinpo_gate_settings', [
    'type' => 'array',
    'default' => ouinpo_gate_default_settings(),
    'sanitize_callback' => static fn($value) => ouinpo_gate_sanitize_settings((array)$value),
  ]);
  register_setting('ouinpo_gate', 'ouinpo_gate_questions', [
    'type' => 'array',
    'default' => ouinpo_gate_default_questions(),
    'sanitize_callback' => 'ouinpo_gate_sanitize_questions',
  ]);
});

function ouinpo_gate_settings(): array {
  ouinpo_gate_ensure_config();
  return ouinpo_gate_sanitize_settings((array)get_option('ouinpo_gate_settings', []));
}

function ouinpo_gate_questions(bool $active_only = false): array {
  ouinpo_gate_ensure_config();
  $questions = ouinpo_gate_sanitize_questions(get_option('ouinpo_gate_questions', []));
  if (!$questions) {
    $questions = ouinpo_gate_default_questions();
  }
  if ($active_only) {
    $questions = array_values(array_filter($questions, static fn($q) => !empty($q['enabled'])));
  }
  return $questions;
}

function ouinpo_gate_question_by_id(string $question_id): ?array {
  foreach (ouinpo_gate_questions(true) as $question) {
    if ($question['id'] === $question_id) {
      return $question;
    }
  }
  return null;
}

function ouinpo_gate_public_questions(): array {
  return array_map(static function($q) {
    return [
      'id' => $q['id'],
      'theme' => $q['domain'] ?: $q['title'],
      'title' => $q['title'],
      'prompt' => $q['prompt'],
      'help' => $q['help'],
      'note' => $q['order'],
      'cooldown' => (int)$q['cooldown'],
    ];
  }, ouinpo_gate_questions(true));
}

function ouinpo_gate_needed(?int $requested = null): int {
  $total = count(ouinpo_gate_questions(true));
  if ($total < 1) {
    return 1;
  }
  if ($requested === null || $requested < 1) {
    return $total;
  }
  return min($requested, $total);
}

function ouinpo_gate_question_ids(bool $active_only = true): array {
  return array_values(array_map(static fn($q) => $q['id'], ouinpo_gate_questions($active_only)));
}

function ouinpo_gate_normalize_solved($raw): array {
  $solved = is_array($raw) ? $raw : (json_decode((string)$raw ?: '[]', true) ?: []);
  $all = ouinpo_gate_questions(false);
  $ids = [];

  foreach ($solved as $value) {
    if (is_int($value) || ctype_digit((string)$value)) {
      $index = (int)$value;
      if (isset($all[$index])) {
        $ids[] = $all[$index]['id'];
      }
      continue;
    }
    $id = sanitize_key((string)$value);
    if ($id !== '') {
      $ids[] = $id;
    }
  }

  return array_values(array_unique($ids));
}

function ouinpo_gate_progress_row(int $uid, string $page) {
  global $wpdb;
  $t_prog = $wpdb->prefix . 'ouinpo_progress';
  return $wpdb->get_row($wpdb->prepare("SELECT * FROM $t_prog WHERE user_id=%d AND page_slug=%s", $uid, $page));
}

function ouinpo_gate_progress_value(int $uid, string $page): int {
  $row = ouinpo_gate_progress_row($uid, $page);
  if (!$row) {
    return 0;
  }
  $active_ids = ouinpo_gate_question_ids(true);
  return count(array_intersect(ouinpo_gate_normalize_solved($row->solved_json ?: '[]'), $active_ids));
}

function ouinpo_gate_next_question_index(int $uid, string $page): int {
  $row = ouinpo_gate_progress_row($uid, $page);
  $solved = $row ? ouinpo_gate_normalize_solved($row->solved_json ?: '[]') : [];
  foreach (ouinpo_gate_questions(true) as $index => $question) {
    if (!in_array($question['id'], $solved, true)) {
      return (int)$index;
    }
  }
  return count(ouinpo_gate_questions(true));
}

function ouinpo_gate_answer_norm(string $value): string {
  $value = trim(wp_strip_all_tags($value));
  $value = remove_accents($value);
  $value = mb_strtolower($value, 'UTF-8');
  $value = preg_replace('/[[:punct:]]+/u', ' ', $value);
  $value = preg_replace('~[\s\p{Z}]+~u', ' ', $value);
  return trim((string)$value);
}

function ouinpo_gate_variants(array $question): array {
  $items = [$question['expected_answer'] ?? ''];
  $variants = preg_split('/\R/u', (string)($question['variants'] ?? '')) ?: [];
  foreach ($variants as $variant) {
    $items[] = $variant;
  }
  return array_values(array_filter(array_map('trim', $items), static fn($v) => $v !== ''));
}

function ouinpo_gate_fallback_match(array $question, string $answer): bool {
  $answer_norm = ouinpo_gate_answer_norm($answer);
  if ($answer_norm === '') {
    return false;
  }
  foreach (ouinpo_gate_variants($question) as $candidate) {
    if ($answer_norm === ouinpo_gate_answer_norm($candidate)) {
      return true;
    }
  }
  return false;
}

function ouinpo_gate_debug_log(string $message, array $context = []): void {
  $settings = ouinpo_gate_settings();
  if (!defined('WP_DEBUG') || !WP_DEBUG || empty($settings['debug_logs'])) {
    return;
  }
  if (class_exists('\Ouinpo\Suite\Core\AiSettings')) {
    \Ouinpo\Suite\Core\AiSettings::debug_log('Gate ' . $message, $context);
    return;
  }
  error_log('[OuInPo Gate] ' . sanitize_text_field($message));
}

function ouinpo_gate_ai_globally_available(): bool {
  return !class_exists('\Ouinpo\Suite\Core\AiSettings') || \Ouinpo\Suite\Core\AiSettings::enabled_for_usage('exercise_correction');
}

function ouinpo_gate_parse_ai_json(string $raw): ?array {
  $raw = trim($raw);
  if ($raw === '') {
    return null;
  }
  if (preg_match('/```(?:json)?\s*(.*?)```/is', $raw, $m)) {
    $raw = trim($m[1]);
  }
  $data = json_decode($raw, true);
  if (!is_array($data) || !array_key_exists('ok', $data)) {
    return null;
  }
  return [
    'ok' => (bool)$data['ok'],
    'confidence' => max(0.0, min(1.0, (float)($data['confidence'] ?? 0))),
    'feedback' => sanitize_text_field((string)($data['feedback'] ?? '')),
    'reason' => sanitize_text_field((string)($data['reason'] ?? '')),
  ];
}

function ouinpo_gate_validate_with_ai(array $question, string $answer, array $settings): ?array {
  if (empty($settings['ai_validation_enabled']) || empty($question['ai_enabled']) || !ouinpo_gate_ai_globally_available()) {
    return null;
  }

  $provider = $settings['ai_provider'];
  $messages = [
    ['role' => 'system', 'content' => (string)$settings['system_prompt']],
    ['role' => 'user', 'content' => wp_json_encode([
      'schema' => ['ok' => 'boolean', 'confidence' => 'number 0..1', 'feedback' => 'court, pedagogique, sans reveler la reponse', 'reason' => 'court'],
      'enonce' => $question['prompt'],
      'reponse_reference' => $question['expected_answer'],
      'variantes_acceptees' => ouinpo_gate_variants($question),
      'criteres_validation' => $question['ai_criteria'],
      'niveau' => $question['school_level'],
      'theme' => $question['domain'],
      'reponse_eleve' => $answer,
    ], JSON_UNESCAPED_UNICODE)],
  ];
  $options = [
    'temperature' => (float)$settings['temperature'],
    'max_tokens' => 220,
    'timeout' => (int)$settings['timeout'],
    'response_format' => ['type' => 'json_object'],
  ];
  if ($settings['ai_model'] !== '') {
    $options['albert_model'] = $settings['ai_model'];
  }

  $start = microtime(true);
  try {
    if ($provider === 'albert' && class_exists('\OuInPo\SegFault\Albert')) {
      $raw = \OuInPo\SegFault\Albert::respond($messages, $options);
    } elseif (class_exists('\OuInPo\SegFault\OpenAI')) {
      $raw = \OuInPo\SegFault\OpenAI::respond($messages, $options);
    } else {
      return null;
    }
  } catch (\Throwable $e) {
    ouinpo_gate_debug_log('AI exception', ['provider' => $provider, 'question_id' => $question['id'], 'error' => $e->getMessage()]);
    return null;
  }

  $parsed = ouinpo_gate_parse_ai_json($raw);
  ouinpo_gate_debug_log('AI validation', [
    'provider' => $provider,
    'question_id' => $question['id'],
    'success' => $parsed ? (int)$parsed['ok'] : 0,
    'duration_ms' => (int)round((microtime(true) - $start) * 1000),
  ]);

  return $parsed;
}

function ouinpo_gate_validate_answer(array $question, string $answer, array $settings): array {
  $ai = ouinpo_gate_validate_with_ai($question, $answer, $settings);
  if (is_array($ai)) {
    return [
      'ok' => (bool)$ai['ok'],
      'feedback' => $ai['feedback'] !== '' ? $ai['feedback'] : ((bool)$ai['ok'] ? $question['success_message'] : $question['failure_message']),
      'method' => 'ai',
    ];
  }

  if (!empty($settings['fallback_exact_enabled']) && !empty($question['fallback_exact'])) {
    $ok = ouinpo_gate_fallback_match($question, $answer);
    return [
      'ok' => $ok,
      'feedback' => $ok ? $question['success_message'] : $question['failure_message'],
      'method' => 'fallback',
    ];
  }

  return [
    'ok' => false,
    'feedback' => $settings['ai_unavailable_message'],
    'method' => 'unavailable',
  ];
}

function ouinpo_gate_client_key(int $uid): string {
  if ($uid > 0) {
    return 'u_' . $uid;
  }
  $ip = sanitize_text_field((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
  $ua = sanitize_text_field(substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120));
  return 'a_' . md5($ip . '|' . $ua);
}

function ouinpo_gate_cooldown_seconds(array $question, array $settings): int {
  $specific = (int)($question['cooldown'] ?? 0);
  return $specific > 0 ? $specific : (int)$settings['global_cooldown'];
}

function ouinpo_gate_cooldown_key(int $uid, string $page, string $question_id): string {
  return 'ouinpo_gate_cd_' . md5(ouinpo_gate_client_key($uid) . '|' . $page . '|' . $question_id);
}

function ouinpo_gate_cooldown_remaining(int $uid, string $page, string $question_id): int {
  $until = (int)get_transient(ouinpo_gate_cooldown_key($uid, $page, $question_id));
  return max(0, $until - time());
}

function ouinpo_gate_set_cooldown(int $uid, string $page, string $question_id, int $seconds): void {
  if ($seconds < 1) {
    return;
  }
  set_transient(ouinpo_gate_cooldown_key($uid, $page, $question_id), time() + $seconds, $seconds + 60);
}

function ouinpo_gate_attempt_key(string $page, string $question_id): string {
  return 'ouinpo_gate_attempts_' . md5($page . '|' . $question_id);
}

function ouinpo_gate_attempts(int $uid, string $page, string $question_id): int {
  return $uid > 0 ? (int)get_user_meta($uid, ouinpo_gate_attempt_key($page, $question_id), true) : 0;
}

function ouinpo_gate_increment_attempts(int $uid, string $page, string $question_id): void {
  if ($uid < 1) {
    return;
  }
  update_user_meta($uid, ouinpo_gate_attempt_key($page, $question_id), ouinpo_gate_attempts($uid, $page, $question_id) + 1);
}

function ouinpo_gate_clear_attempts(int $uid, string $page, string $question_id): void {
  if ($uid > 0) {
    delete_user_meta($uid, ouinpo_gate_attempt_key($page, $question_id));
  }
}

/* ================= Shortcode principal : [ouinpo_gate] =================
   Usage : [ouinpo_gate page="sample-page" needed="42" reveal="embed|link|redirect"]
*/
add_shortcode('ouinpo_gate', function($atts){
  ouinpo_gate_enqueue_assets();
  ouinpo_gate_enqueue_signpad_script();

  if(!is_user_logged_in()){
    return '<p>🔒 Cette quête ouinpienne est réservée aux membres. <a href="'.esc_url(wp_login_url(get_permalink())).'">Connecte-toi</a> pour commencer.</a></p>';
  }
  $settings = ouinpo_gate_settings();
  if (empty($settings['enabled'])) {
    return '<p>Gate est desactive pour le moment.</p>';
  }

  $a = shortcode_atts(['page'=>'sample-page','needed'=>0,'reveal'=>'embed'], $atts);
  $page    = sanitize_title($a['page']);
  $needed  = ouinpo_gate_needed((int)$a['needed']);
  $reveal  = in_array($a['reveal'], ['embed','link','redirect'], true) ? $a['reveal'] : 'embed';
  $uid     = get_current_user_id();

  $progress = ouinpo_gate_progress_value($uid, $page);
  $current_index = ouinpo_gate_next_question_index($uid, $page);

  $post  = get_page_by_path($page);
  $plink = $post ? get_permalink($post) : home_url('/'.$page.'/');

  $public  = ouinpo_gate_public_questions();
  $nonce = wp_create_nonce('ouinpo_nonce');

  ob_start(); ?>
  <div id="ouinpo-game" data-page="<?php echo esc_attr($page);?>" data-needed="<?php echo $needed;?>"
       data-reveal="<?php echo esc_attr($reveal);?>" data-plink="<?php echo esc_url($plink);?>"
       data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
       data-nonce="<?php echo esc_attr($nonce); ?>"
       data-progress="<?php echo (int)$progress; ?>"
       data-current="<?php echo (int)$current_index; ?>"
       data-enigmes="<?php echo esc_attr(wp_json_encode($public, JSON_UNESCAPED_UNICODE)); ?>"
       data-redirect-url="<?php echo ($progress >= $needed && $reveal === 'redirect') ? esc_url($plink) : ''; ?>">
    <blockquote class="eldritch">« Quarante-deux verrous, un seul sourire : le tien. »</blockquote>

    <div id="ouinpo-progress" class="ouinpo-gate-progress">
      Progression : <strong><span id="ouinpo-count"><?php echo $progress;?></span> / <span id="ouinpo-needed"><?php echo $needed;?></span></strong>
    </div>

    <div id="ouinpo-question" class="ouinpo-gate-question"></div>

    <div id="ouinpo-final" class="ouinpo-gate-final<?php echo ($progress >= $needed ? '' : ' ouinpo-gate-hidden'); ?>">
      <h2>🎉 Félicitations !</h2>
      <div id="ouinpo-secret-content">
        <?php if($progress >= $needed): ?>
          <?php if($reveal === 'embed'): ?>
            <?php echo $post ? apply_filters('the_content', $post->post_content) : '<em>Contenu introuvable.</em>'; ?>
          <?php elseif($reveal === 'link'): ?>
            <p><a class="button" href="<?php echo esc_url($plink); ?>">🚪 Accéder à la page secrète</a></p>
          <?php else: /* redirect */ ?>
            <p>Redirection en cours… Si rien ne se passe, <a href="<?php echo esc_url($plink); ?>">clique ici</a>.</p>
          <?php endif; ?>
        <?php else: ?>
          <em>Atteins le seuil pour révéler le contenu…</em>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php
  return ob_get_clean();
});

/* ================= AJAX : vérification ================= */
add_action('wp_ajax_ouinpo_check','ouinpo_check_configured');

function ouinpo_check_configured(){
  if(!is_user_logged_in()) wp_send_json(['ok'=>false,'msg'=>'login']);
  check_ajax_referer('ouinpo_nonce','nonce');

  $uid = get_current_user_id();
  $index = max(0, intval($_POST['index'] ?? -1));
  $question_id = sanitize_key((string)wp_unslash($_POST['question_id'] ?? ''));

  if (isset($_POST['payload'])) {
    $answer = base64_decode(wp_unslash($_POST['payload']), true);
    $answer = is_string($answer) ? $answer : '';
  } elseif (isset($_POST['answer'])) {
    $answer = wp_unslash($_POST['answer']);
  } else {
    $answer = '';
  }

  $page = sanitize_title(wp_unslash($_POST['page'] ?? 'sample-page'));
  $questions = ouinpo_gate_questions(true);
  if ($question_id === '' && isset($questions[$index])) {
    $question_id = $questions[$index]['id'];
  }
  $question = $question_id !== '' ? ouinpo_gate_question_by_id($question_id) : null;
  if (!$question) {
    wp_send_json(['ok'=>false,'msg'=>'bad question']);
  }

  global $wpdb;
  $t_prog = $wpdb->prefix.'ouinpo_progress';
  $t_logs = $wpdb->prefix.'ouinpo_logs';

  $settings = ouinpo_gate_settings();
  $remaining = ouinpo_gate_cooldown_remaining($uid, $page, $question_id);
  if ($remaining > 0) {
    ouinpo_gate_debug_log('cooldown blocked', ['user_id' => $uid, 'question_id' => $question_id, 'retry_after' => $remaining]);
    wp_send_json(['ok'=>false,'error'=>'cooldown','retry_after'=>$remaining,'feedback'=>'Tu pourras reessayer dans '.$remaining.' s.']);
  }

  $max_attempts = (int)($question['max_attempts'] ?: $settings['global_max_attempts']);
  if ($max_attempts > 0 && ouinpo_gate_attempts($uid, $page, $question_id) >= $max_attempts) {
    wp_send_json(['ok'=>false,'error'=>'max_attempts','feedback'=>'Nombre maximal de tentatives atteint pour cette enigme.']);
  }

  $validation = ouinpo_gate_validate_answer($question, (string)$answer, $settings);
  $cooldown = ouinpo_gate_cooldown_seconds($question, $settings);
  ouinpo_gate_set_cooldown($uid, $page, $question_id, $cooldown);

  $row = ouinpo_gate_progress_row($uid, $page);
  $prev_progress = $row ? ouinpo_gate_progress_value($uid, $page) : 0;
  $progress = $prev_progress;
  $ok = !empty($validation['ok']);

  if ($ok) {
    $solved = $row ? ouinpo_gate_normalize_solved($row->solved_json ?: '[]') : [];
    if (!in_array($question_id, $solved, true)) {
      $solved[] = $question_id;
      sort($solved);
      $progress = count(array_intersect($solved, ouinpo_gate_question_ids(true)));

      if ($row) {
        $wpdb->update($t_prog, [
          'solved_json' => wp_json_encode($solved),
          'progress' => $progress,
          'updated_at' => current_time('mysql'),
        ], ['id' => $row->id]);
      } else {
        $wpdb->insert($t_prog, [
          'user_id' => $uid,
          'page_slug' => $page,
          'solved_json' => wp_json_encode($solved),
          'progress' => $progress,
          'updated_at' => current_time('mysql'),
        ]);
      }

      $wpdb->insert($t_logs, [
        'user_id' => $uid,
        'page_slug' => $page,
        'riddle_index' => $index,
        'ok' => 1,
        'answer_norm' => '[redacted:'.$validation['method'].']',
        'created_at' => current_time('mysql'),
      ]);

      $needed = ouinpo_gate_needed();
      if ($progress >= $needed && $prev_progress < $needed) {
        $t_user_badges = $wpdb->prefix.'ouin_exo_user_badges';
        $badge_id_42 = 86;
        $already = $wpdb->get_var($wpdb->prepare(
          "SELECT 1 FROM $t_user_badges WHERE user_id=%d AND badge_id=%d LIMIT 1",
          $uid,
          $badge_id_42
        ));
        if (!$already) {
          $wpdb->insert($t_user_badges, [
            'user_id' => $uid,
            'badge_id' => $badge_id_42,
            'awarded_at' => current_time('mysql'),
            'source' => 'auto',
          ]);
        }
        update_user_meta($uid, 'ouinpo_pass_42', current_time('mysql'));
      }
    }

    ouinpo_gate_clear_attempts($uid, $page, $question_id);
    wp_send_json(['ok'=>true,'progress'=>$progress,'feedback'=>$validation['feedback'],'retry_after'=>$cooldown]);
  }

  ouinpo_gate_increment_attempts($uid, $page, $question_id);
  $wpdb->insert($t_logs, [
    'user_id' => $uid,
    'page_slug' => $page,
    'riddle_index' => $index,
    'ok' => 0,
    'answer_norm' => '[redacted:'.$validation['method'].']',
    'created_at' => current_time('mysql'),
  ]);
  wp_send_json(['ok'=>false,'feedback'=>$validation['feedback'],'retry_after'=>$cooldown]);
}

function ouinpo_check(){
  ouinpo_check_configured();
}
/* ================= AJAX : contenu secret ================= */
add_action('wp_ajax_ouinpo_secret','ouinpo_secret');
function ouinpo_secret(){
  if(!is_user_logged_in()) { wp_die('login'); }
  check_ajax_referer('ouinpo_nonce','nonce');

  $uid  = get_current_user_id();
  $page = sanitize_title( wp_unslash( $_POST['page'] ?? 'sample-page' ) );
  $needed = ouinpo_gate_needed();
  $p = ouinpo_gate_progress_value($uid, $page);
  if($p >= $needed){
    $post = get_page_by_path($page);
    echo $post ? apply_filters('the_content', $post->post_content) : '<em>Contenu introuvable.</em>';
  } else {
    echo '<em>Pas encore…</em>';
  }
  wp_die();
}

/* ================= Shortcode signature : [ouinpo_signpad] =================
   Usage: [ouinpo_signpad page="sample-page" needed="42" show_list="1"]
*/
add_shortcode('ouinpo_signpad', function($atts){
  ouinpo_gate_enqueue_assets();

  if(!is_user_logged_in()){
    return '<p>🔒 Réservé aux membres connectés.</p>';
  }
  $a = shortcode_atts(['page'=>'sample-page','needed'=>0,'show_list'=>'1'], $atts);
  $page      = sanitize_title($a['page']);
  $needed    = ouinpo_gate_needed((int)$a['needed']);
  $show_list = $a['show_list'] === '1';
  $uid       = get_current_user_id();

  global $wpdb;
  $progress = ouinpo_gate_progress_value($uid, $page);
  
  if($progress >= $needed){ update_user_meta($uid, 'ouinpo_pass_42', current_time('mysql')); }
if($progress < $needed){
    return '<p class="lab-note">⏳ Pas encore… Termine la quête pour signer cette page.</p>';
  }

  // déjà signé ?
  $t_sign = $wpdb->prefix.'ouinpo_signatures';
  $already = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $t_sign WHERE user_id=%d AND page_slug=%s ORDER BY date_time DESC, id DESC LIMIT 1",
    $uid, $page
  ));

  $nonce = wp_create_nonce('ouinpo_nonce');
  $certURL = esc_url( admin_url('admin-ajax.php?action=ouinpo_certificate&page='.$page.'&nonce='.$nonce) );

  ob_start(); ?>
  <div class="eldritch" id="ouinpo-signpad">
    <h3>📜 Registre des Trouveurs</h3>

    <?php if($already): ?>
      <p class="lab-note">✅ Vous avez déjà signé le <time><?php echo esc_html($already->date_time);?></time>.
      Merci <strong><?php echo esc_html($already->nom);?></strong><?php echo $already->pseudo? ' (alias <em>'.esc_html($already->pseudo).'</em>)':''; ?>.</p>
      <p><a class="button" target="_blank" rel="noopener" href="<?php echo $certURL; ?>">📜 Télécharger mon certificat de réussite</a></p>
    <?php else: ?>
      <?php ouinpo_gate_enqueue_signpad_script(); ?>
      <form id="ouinpo-sign-form" class="ouinpo-sign-form" data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>" data-certificate-url="<?php echo $certURL; ?>">
        <label>Nom (réel) : <input name="nom" required></label><br>
        <label>Pseudo : <input name="pseudo"></label><br>
        <label>Message : <textarea name="message" rows="3"></textarea></label><br>
        <input type="hidden" name="page" value="<?php echo esc_attr($page);?>">
        <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce);?>">
        <button type="submit">Signer</button>
      </form>
      <div id="ouinpo-sign-result" class="ouinpo-gate-sign-result"></div>
    <?php endif; ?>

    <?php if($show_list):
      $signs  = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $t_sign WHERE page_slug=%s ORDER BY date_time DESC LIMIT 100",
        $page
      ));
      if($signs): ?>
        <h4 class="ouinpo-gate-sign-title">Signatures récentes</h4>
        <ul>
          <?php foreach($signs as $S): ?>
            <li>
              <strong><?php echo esc_html($S->nom); ?></strong>
              <?php if($S->pseudo) echo ' (<em>'.esc_html($S->pseudo).'</em>)'; ?>
              — <time><?php echo esc_html($S->date_time); ?></time><br>
              <?php if($S->message) echo '<span>'.nl2br(esc_html($S->message)).'</span>'; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php else: ?>
        <p><em>Aucune signature pour le moment. Serez-vous le premier ?</em></p>
      <?php endif; ?>
    <?php endif; ?>
  </div>


  <?php
  return ob_get_clean();
});

/* ================= AJAX: signature ================= */
add_action('wp_ajax_ouinpo_sign','ouinpo_sign');
add_action('wp_ajax_nopriv_ouinpo_sign','ouinpo_sign'); // retour JSON clair si déconnecté
function ouinpo_sign(){
  if(!is_user_logged_in()) wp_send_json(['ok'=>false,'msg'=>'login']);
  check_ajax_referer('ouinpo_nonce','nonce');
  global $wpdb;
  $t_sign=$wpdb->prefix.'ouinpo_signatures';
  $uid=get_current_user_id();
  $nom = sanitize_text_field( wp_unslash( $_POST['nom'] ?? '' ) );
  $pseudo = sanitize_text_field( wp_unslash( $_POST['pseudo'] ?? '' ) );
  $message = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
  $page = sanitize_title( wp_unslash( $_POST['page'] ?? 'sample-page' ) );
  if(empty($nom)) wp_send_json(['ok'=>false,'msg'=>'Nom requis']);
  $wpdb->insert($t_sign,[
    'user_id'=>$uid,'page_slug'=>$page,'nom'=>$nom,'pseudo'=>$pseudo,'message'=>$message,
    'ip'=>($_SERVER['REMOTE_ADDR']??''),'ua'=>($_SERVER['HTTP_USER_AGENT']??''),'date_time'=>current_time('mysql')
  ]);
  wp_send_json(['ok'=>true]);
}

/* ================= AJAX: Certificat PDF (A4 paysage) ================= */
add_action('wp_ajax_ouinpo_certificate','ouinpo_certificate');
function ouinpo_certificate(){
  if(!is_user_logged_in()) wp_die('login');
  check_ajax_referer('ouinpo_nonce','nonce');
  global $wpdb;
  $uid  = get_current_user_id();
  $page = sanitize_title($_GET['page'] ?? 'sample-page');
  $t_sign = $wpdb->prefix.'ouinpo_signatures';

  $needed = ouinpo_gate_needed();
  $progress = ouinpo_gate_progress_value($uid, $page);
  if($progress < $needed){ wp_die('not-complete'); }

  $row = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $t_sign WHERE user_id=%d AND page_slug=%s ORDER BY date_time DESC, id DESC LIMIT 1",
    $uid, $page
  ));
  if(!$row){ wp_die('no-sign'); }

  $base = plugin_dir_path(__FILE__);
  $fpdf = $base.'fpdf/fpdf.php';
  if(!file_exists($fpdf)){ wp_die('fpdf-missing'); }
  require_once $fpdf;

  class OuinpoPDF_L extends FPDF{
    protected $angle=0;
    function Rotate($angle,$x=-1,$y=-1){
      if($x==-1)$x=$this->x; if($y==-1)$y=$this->y;
      if($this->angle!=0)$this->_out('Q'); $this->angle=$angle;
      if($angle!=0){ $angle*=M_PI/180; $c=cos($angle); $s=sin($angle);
        $cx=$x*$this->k; $cy=($this->h-$y)*$this->k;
        $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.5F %.5F cm 1 0 0 1 %.5F %.5F cm',
          $c,$s,-$s,$c,$cx,$cy,-$cx,-$cy));
      }
    }
    function RotatedText($x,$y,$txt,$angle){ $this->Rotate($angle,$x,$y); $this->Text($x,$y,$txt); $this->Rotate(0); }
  }

  $pdf=new OuinpoPDF_L('L','mm','A4');
  $pdf->SetTitle('Certificat OuInPo');
  $pdf->AddPage();

  $bg   = $base.'assets/cert/parchment_bg.jpg';
  $logo = $base.'assets/cert/logo_ouinpo.png';
  if(file_exists($bg))   $pdf->Image($bg, 0, 0, 297, 210);
  if(file_exists($logo)) $pdf->Image($logo, 20, 20, 64, 64);

  // décor
  // $pdf->SetDrawColor(60,100,60); $pdf->SetLineWidth(0.8); $pdf->Rect(10,10,277,190);
  // $pdf->SetDrawColor(120,160,120); $pdf->Rect(13,13,271,184);
  // $pdf->SetTextColor(120,180,120); $pdf->SetFont('Times','B',60);
  // $pdf->RotatedText(80,160,utf8_decode('OUINPO'),20);
  // $pdf->SetTextColor(0,0,0);

  $post = get_page_by_path($page);
  $titre_page = $post ? $post->post_title : 'OuInPo';
  $dateStr = date_i18n('d F Y', strtotime($row->date_time));
  $certnum = 'OUINPO-'.date('Ymd', strtotime($row->date_time)).'-'.$row->id;

  $pdf->SetY(24);
  $pdf->SetFont('Times','B',24);
  $pdf->Cell(0,12,utf8_decode("CERTIFICAT DE RÉUSSITE DE L'OuInPo"),0,1,'C');
  $pdf->SetFont('Times','',14);
  $pdf->Cell(0,8,utf8_decode("Récompense : ".$titre_page),0,1,'C');
  $pdf->Ln(6);

  $pdf->SetFont('Times','',14);
  $pdf->Cell(0,8,utf8_decode("Ce document atteste que :"),0,1,'C');
  $pdf->Ln(2);
  $pdf->SetFont('Times','B',30);
  $pdf->SetTextColor(40,100,60);
  $pdf->Cell(0,14,utf8_decode($row->nom),0,1,'C');
  $pdf->SetTextColor(0,0,0);
  if(!empty($row->pseudo)){ $pdf->SetFont('Times','I',13); $pdf->Cell(0,8,utf8_decode('alias : '.$row->pseudo),0,1,'C'); }

  $pdf->SetFont('Courier','',11); $pdf->SetTextColor(90,90,90);
  $pdf->Cell(0,6,utf8_decode('Certificat n° '.$certnum),0,1,'C');
  $pdf->SetTextColor(0,0,0);

  $pdf->Ln(6);
  $pdf->SetFont('Times','',14);
  $pdf->MultiCell(0,8,utf8_decode("a brillamment franchi les ".$needed." épreuves actives de l'OuInPo.\n\nPar ce certificat, l'intéressé(e) est déclaré(e) membre honoraire du Cercle des Débogueurs Métaphysiques et gardien(ne) des variables instables."),0,'C');

  $pdf->Ln(6);
  $pdf->SetDrawColor(120,160,120); $pdf->SetLineWidth(0.4);
  $x = 60; $w = 177; $y = $pdf->GetY();
  $pdf->Rect($x, $y, $w, 20);
  $pdf->SetFont('Times','I',12);
  $pdf->SetXY($x+4, $y+4);
  $pdf->MultiCell($w-8,6,utf8_decode("« L'erreur est humaine, mais le segfault est divin. »"),0,'C');

  $pdf->Ln(12);
  $pdf->SetFont('Times','B',13);
  $site_name = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
  $pdf->Cell(0,8,utf8_decode("Fait à ".$site_name.", le ".$dateStr),0,1,'C');
  $pdf->SetFont('Times','',12);
  $pdf->Cell(0,8,utf8_decode("L'equipe pedagogique"),0,1,'C');
  $pdf->SetDrawColor(60,100,60); $pdf->SetLineWidth(0.8);
  $pdf->Line(110, 180, 187, 180);

  header('Content-Type: application/pdf');
  header('Content-Disposition: attachment; filename="Certificat_OuInPo.pdf"');
  $pdf->Output();
  exit;
}

/* ================= Admin : suivi & export ================= */
add_action('admin_menu', function(){
  $parent = defined('OUINPO_SUITE_ADMIN_SLUG')
    ? OUINPO_SUITE_ADMIN_SLUG
    : 'ouinpo';

  if (!defined('OUINPO_SUITE_ADMIN_SLUG')) {
    add_menu_page(
      'Ouinpo',
      'Ouinpo',
      \Ouinpo\Suite\Core\Capabilities::MANAGE_AI,
      'ouinpo',
      'ouinpo_admin_progress',
      'dashicons-shield',
      65
    );
  }

  add_submenu_page(
    $parent,
    'Ouinpo / Gate',
    'Gate',
    \Ouinpo\Suite\Core\Capabilities::MANAGE_AI,
    'ouinpo',
    'ouinpo_admin_progress'
  );
});

function ouinpo_gate_admin_handle_post(): void {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['ouinpo_gate_admin_action'])) {
    return;
  }
  if(!\Ouinpo\Suite\Core\Capabilities::can(\Ouinpo\Suite\Core\Capabilities::MANAGE_AI)) {
    wp_die('Nope');
  }
  check_admin_referer('ouinpo_gate_admin');

  $action = sanitize_key((string)wp_unslash($_POST['ouinpo_gate_admin_action']));
  if ($action === 'save') {
    $settings = isset($_POST['gate_settings']) && is_array($_POST['gate_settings'])
      ? ouinpo_gate_sanitize_settings(wp_unslash($_POST['gate_settings']))
      : ouinpo_gate_default_settings();
    $questions = isset($_POST['gate_questions']) && is_array($_POST['gate_questions'])
      ? ouinpo_gate_sanitize_questions(wp_unslash($_POST['gate_questions']))
      : [];

    if (isset($_POST['gate_new_question']) && is_array($_POST['gate_new_question'])) {
      $new = ouinpo_gate_sanitize_question(wp_unslash($_POST['gate_new_question']), count($questions) + 1);
      if ($new !== null) {
        $questions[] = $new;
        $questions = ouinpo_gate_sanitize_questions($questions);
      }
    }

    update_option('ouinpo_gate_settings', $settings, false);
    update_option('ouinpo_gate_questions', $questions, false);
    update_option('ouinpo_gate_schema_version', '1.0.0', false);
    echo '<div class="notice notice-success is-dismissible"><p>Configuration Gate enregistree.</p></div>';
  } elseif ($action === 'restore_defaults') {
    update_option('ouinpo_gate_questions', ouinpo_gate_default_questions(), false);
    update_option('ouinpo_gate_schema_version', '1.0.0', false);
    echo '<div class="notice notice-success is-dismissible"><p>Enigmes Gate par defaut restaurees. Les progressions eleves sont conservees.</p></div>';
  }
}

function ouinpo_gate_admin_checked($value): string {
  return checked(1, (int)$value, false);
}

function ouinpo_gate_admin_question_fields(array $q, string $name, bool $is_new = false): void {
  $levels = ['seconde' => 'Seconde', 'premiere' => 'Premiere', 'terminale' => 'Terminale', 'transversal' => 'Transversal'];
  ?>
  <tr>
    <td><input type="number" name="<?php echo esc_attr($name); ?>[order]" value="<?php echo esc_attr((int)($q['order'] ?? 0)); ?>" class="small-text"></td>
    <td>
      <label><input type="checkbox" name="<?php echo esc_attr($name); ?>[enabled]" value="1" <?php echo ouinpo_gate_admin_checked($q['enabled'] ?? 0); ?>> active</label><br>
      <?php if (!$is_new): ?><label><input type="checkbox" name="<?php echo esc_attr($name); ?>[_delete]" value="1"> supprimer</label><?php endif; ?>
    </td>
    <td>
      <input type="text" name="<?php echo esc_attr($name); ?>[id]" value="<?php echo esc_attr($q['id'] ?? ''); ?>" class="regular-text" placeholder="gate-001">
      <p><input type="text" name="<?php echo esc_attr($name); ?>[title]" value="<?php echo esc_attr($q['title'] ?? ''); ?>" class="regular-text" placeholder="Titre court"></p>
      <p><input type="text" name="<?php echo esc_attr($name); ?>[domain]" value="<?php echo esc_attr($q['domain'] ?? ''); ?>" class="regular-text" placeholder="Theme / domaine"></p>
      <select name="<?php echo esc_attr($name); ?>[school_level]">
        <?php foreach ($levels as $key => $label): ?>
          <option value="<?php echo esc_attr($key); ?>" <?php selected($key, $q['school_level'] ?? 'transversal'); ?>><?php echo esc_html($label); ?></option>
        <?php endforeach; ?>
      </select>
    </td>
    <td>
      <textarea name="<?php echo esc_attr($name); ?>[prompt]" rows="4" class="large-text" placeholder="Enonce affiche a l'eleve"><?php echo esc_textarea($q['prompt'] ?? ''); ?></textarea>
      <textarea name="<?php echo esc_attr($name); ?>[help]" rows="2" class="large-text" placeholder="Aide optionnelle"><?php echo esc_textarea($q['help'] ?? ''); ?></textarea>
    </td>
    <td>
      <textarea name="<?php echo esc_attr($name); ?>[expected_answer]" rows="2" class="large-text" placeholder="Reponse de reference"><?php echo esc_textarea($q['expected_answer'] ?? ''); ?></textarea>
      <textarea name="<?php echo esc_attr($name); ?>[variants]" rows="3" class="large-text" placeholder="Variantes acceptees, une par ligne"><?php echo esc_textarea($q['variants'] ?? ''); ?></textarea>
      <textarea name="<?php echo esc_attr($name); ?>[ai_criteria]" rows="3" class="large-text" placeholder="Criteres IA"><?php echo esc_textarea($q['ai_criteria'] ?? ''); ?></textarea>
    </td>
    <td>
      <label><input type="checkbox" name="<?php echo esc_attr($name); ?>[ai_enabled]" value="1" <?php echo ouinpo_gate_admin_checked($q['ai_enabled'] ?? 0); ?>> IA</label><br>
      <label><input type="checkbox" name="<?php echo esc_attr($name); ?>[fallback_exact]" value="1" <?php echo ouinpo_gate_admin_checked($q['fallback_exact'] ?? 0); ?>> fallback exact</label>
      <p>Cooldown <input type="number" name="<?php echo esc_attr($name); ?>[cooldown]" value="<?php echo esc_attr((int)($q['cooldown'] ?? 0)); ?>" class="small-text"> s</p>
      <p>Tentatives <input type="number" name="<?php echo esc_attr($name); ?>[max_attempts]" value="<?php echo esc_attr((int)($q['max_attempts'] ?? 0)); ?>" class="small-text"></p>
    </td>
    <td>
      <input type="text" name="<?php echo esc_attr($name); ?>[success_message]" value="<?php echo esc_attr($q['success_message'] ?? ''); ?>" class="regular-text" placeholder="Message reussite">
      <p><input type="text" name="<?php echo esc_attr($name); ?>[failure_message]" value="<?php echo esc_attr($q['failure_message'] ?? ''); ?>" class="regular-text" placeholder="Message echec"></p>
    </td>
  </tr>
  <?php
}

function ouinpo_gate_admin_settings_page(): void {
  $settings = ouinpo_gate_settings();
  $questions = ouinpo_gate_questions(false);
  ?>
  <form method="post">
    <?php wp_nonce_field('ouinpo_gate_admin'); ?>
    <input type="hidden" name="ouinpo_gate_admin_action" value="save">
    <h2>Reglages generaux Gate</h2>
    <table class="form-table" role="presentation"><tbody>
      <tr><th>Activation</th><td>
        <label><input type="checkbox" name="gate_settings[enabled]" value="1" <?php echo ouinpo_gate_admin_checked($settings['enabled']); ?>> Gate actif</label><br>
        <label><input type="checkbox" name="gate_settings[ai_validation_enabled]" value="1" <?php echo ouinpo_gate_admin_checked($settings['ai_validation_enabled']); ?>> validation IA Gate</label><br>
        <label><input type="checkbox" name="gate_settings[fallback_exact_enabled]" value="1" <?php echo ouinpo_gate_admin_checked($settings['fallback_exact_enabled']); ?>> fallback exact global si IA indisponible</label><br>
        <label><input type="checkbox" name="gate_settings[debug_logs]" value="1" <?php echo ouinpo_gate_admin_checked($settings['debug_logs']); ?>> debug Gate (necessite WP_DEBUG)</label>
      </td></tr>
      <tr><th>IA</th><td>
        <select name="gate_settings[ai_provider]">
          <?php foreach (['inherit' => 'Heriter', 'albert' => 'Albert', 'openai' => 'OpenAI'] as $key => $label): ?>
            <option value="<?php echo esc_attr($key); ?>" <?php selected($key, $settings['ai_provider']); ?>><?php echo esc_html($label); ?></option>
          <?php endforeach; ?>
        </select>
        <input type="text" name="gate_settings[ai_model]" value="<?php echo esc_attr($settings['ai_model']); ?>" placeholder="Modele IA Gate" class="regular-text">
        <p>Temperature <input type="number" step="0.1" min="0" max="2" name="gate_settings[temperature]" value="<?php echo esc_attr($settings['temperature']); ?>" class="small-text">
        Timeout <input type="number" min="5" max="120" name="gate_settings[timeout]" value="<?php echo esc_attr($settings['timeout']); ?>" class="small-text"> s</p>
      </td></tr>
      <tr><th>Anti-spam</th><td>
        Cooldown global <input type="number" min="0" max="3600" name="gate_settings[global_cooldown]" value="<?php echo esc_attr($settings['global_cooldown']); ?>" class="small-text"> s
        Tentatives globales <input type="number" min="0" max="100" name="gate_settings[global_max_attempts]" value="<?php echo esc_attr($settings['global_max_attempts']); ?>" class="small-text">
      </td></tr>
      <tr><th>Messages</th><td>
        <input type="text" name="gate_settings[ai_unavailable_message]" value="<?php echo esc_attr($settings['ai_unavailable_message']); ?>" class="large-text">
        <textarea name="gate_settings[system_prompt]" rows="5" class="large-text"><?php echo esc_textarea($settings['system_prompt']); ?></textarea>
      </td></tr>
    </tbody></table>

    <h2>Enigmes configurables</h2>
    <p>Les reponses de reference et criteres ne sont jamais envoyes au HTML public ; ils servent uniquement a la validation serveur.</p>
    <table class="widefat striped">
      <thead><tr><th>Ordre</th><th>Etat</th><th>Identite</th><th>Enonce</th><th>Validation</th><th>Options</th><th>Feedback</th></tr></thead>
      <tbody>
        <?php foreach ($questions as $i => $q): ouinpo_gate_admin_question_fields($q, 'gate_questions['.$i.']'); endforeach; ?>
      </tbody>
    </table>
    <h3>Ajouter une enigme</h3>
    <table class="widefat"><tbody>
      <?php ouinpo_gate_admin_question_fields(['enabled'=>0,'ai_enabled'=>1,'fallback_exact'=>1,'school_level'=>'transversal','success_message'=>'Reponse acceptee.','failure_message'=>"Ce n'est pas encore ca."], 'gate_new_question', true); ?>
    </tbody></table>
    <?php submit_button('Enregistrer Gate'); ?>
  </form>
  <form method="post">
    <?php wp_nonce_field('ouinpo_gate_admin'); ?>
    <input type="hidden" name="ouinpo_gate_admin_action" value="restore_defaults">
    <?php submit_button('Restaurer les enigmes par defaut', 'secondary'); ?>
  </form>
  <?php
}

function ouinpo_admin_progress(){
  if(!\Ouinpo\Suite\Core\Capabilities::can(\Ouinpo\Suite\Core\Capabilities::MANAGE_AI)) { wp_die('Nope'); }
  ouinpo_gate_admin_handle_post();
  global $wpdb; $t_prog=$wpdb->prefix.'ouinpo_progress'; $t_logs=$wpdb->prefix.'ouinpo_logs'; $t_sign=$wpdb->prefix.'ouinpo_signatures';
  $page = sanitize_title($_GET['page_slug'] ?? 'sample-page');
  $tab = sanitize_key((string)($_GET['tab'] ?? 'progress'));
  if(isset($_GET['export']) && $_GET['export']==='csv'){
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=ouinpo_progress_'.$page.'.csv');
    $out = fopen('php://output','w');
    fputcsv($out, ['user_id','login','progress','solved','updated_at']);
    $rows = $wpdb->get_results($wpdb->prepare("SELECT p.*, u.user_login FROM $t_prog p JOIN {$wpdb->users} u ON u.ID=p.user_id WHERE page_slug=%s ORDER BY updated_at DESC",$page), ARRAY_A);
    foreach($rows as $r){ fputcsv($out, [$r['user_id'],$r['user_login'],$r['progress'],$r['solved_json'],$r['updated_at']]); }
    fclose($out); exit;
  }
  echo '<div class="wrap"><h1>Ouinpo Gate</h1>';
  echo '<h2 class="nav-tab-wrapper">';
  echo '<a class="nav-tab '.($tab === 'progress' ? 'nav-tab-active' : '').'" href="'.esc_url(admin_url('admin.php?page=ouinpo&tab=progress&page_slug='.$page)).'">Suivi</a>';
  echo '<a class="nav-tab '.($tab === 'settings' ? 'nav-tab-active' : '').'" href="'.esc_url(admin_url('admin.php?page=ouinpo&tab=settings&page_slug='.$page)).'">Configuration</a>';
  echo '</h2>';
  if ($tab === 'settings') {
    ouinpo_gate_admin_settings_page();
    echo '</div>';
    return;
  }
  $needed = ouinpo_gate_needed();
  echo '<h2>Progression (page: '.esc_html($page).')</h2>';
  echo '<p><a class="button" href="'.esc_url(admin_url('admin.php?page=ouinpo&page_slug='.$page.'&export=csv')).'">Exporter CSV</a></p>';
  $rows = $wpdb->get_results($wpdb->prepare("SELECT p.*, u.user_login FROM $t_prog p JOIN {$wpdb->users} u ON u.ID=p.user_id WHERE page_slug=%s ORDER BY updated_at DESC",$page));
  echo '<table class="widefat"><thead><tr><th>Utilisateur</th><th>Progression</th><th>Indices résolus</th><th>Dernière MAJ</th></tr></thead><tbody>';
  foreach($rows as $r){
    echo '<tr><td>'.esc_html($r->user_login).' (#'.intval($r->user_id).')</td>'.
         '<td>'.ouinpo_gate_progress_value((int)$r->user_id, $page).'/'.intval($needed).'</td>'.
         '<td><code>'.esc_html($r->solved_json).'</code></td>'.
         '<td>'.esc_html($r->updated_at).'</td></tr>';
  }
  echo '</tbody></table>';

  $logs = $wpdb->get_results($wpdb->prepare("SELECT l.*, u.user_login FROM $t_logs l JOIN {$wpdb->users} u ON u.ID=l.user_id WHERE page_slug=%s ORDER BY created_at DESC LIMIT 50",$page));
  echo '<h2>Dernières résolutions</h2><table class="widefat"><thead><tr><th>Date</th><th>Utilisateur</th><th>Énigme</th><th>Methode</th></tr></thead><tbody>';
  foreach($logs as $L){
    echo '<tr><td>'.esc_html($L->created_at).'</td><td>'.esc_html($L->user_login).'</td><td>#'.intval($L->riddle_index+1).'</td><td><code>'.esc_html($L->answer_norm).'</code></td></tr>';
  }
  echo '</tbody></table>';

  $signs = $wpdb->get_results($wpdb->prepare("SELECT s.*, u.user_login FROM $t_sign s JOIN {$wpdb->users} u ON u.ID=s.user_id WHERE page_slug=%s ORDER BY date_time DESC",$page));
  echo '<h2>Signatures</h2><table class="widefat"><thead><tr><th>Date</th><th>Utilisateur</th><th>Nom</th><th>Pseudo</th><th>Message</th></tr></thead><tbody>';
  foreach($signs as $S){
    echo '<tr><td>'.esc_html($S->date_time).'</td><td>'.esc_html($S->user_login).'</td><td>'.esc_html($S->nom).'</td><td>'.esc_html($S->pseudo).'</td><td>'.esc_html($S->message).'</td></tr>';
  }
  echo '</tbody></table></div>';
}

/* ================= Shortcode: [ouinpo_hint] ... [/ouinpo_hint] ================= */
add_shortcode('ouinpo_hint', function($atts, $content = ''){
  if( is_user_logged_in() ){
    $done = get_user_meta( get_current_user_id(), 'ouinpo_pass_42', true );
    if( ! empty($done) ){
      return '';
    }
  }
  return do_shortcode($content);
});


