<?php

namespace OuInPo\SegFault;



defined('ABSPATH') || exit;



class OpenAI {

  // --- Config ---

  static function api_key(): string {

    return \Ouinpo\Suite\Core\AiSettings::secret('ouinpo_sf_openai_api_key');

  }

  static function chat_model(): string {

    $m = trim((string) get_option('ouinpo_sf_model', ''));
    if ($m === '') {
      $m = trim((string) get_option('ouinpo_ai_chat_model', 'gpt-5-mini'));
    }

    return $m !== '' ? $m : 'gpt-5-mini';

  }

  static function embed_model(): string {

    $m = (string) get_option('ouinpo_sf_embed_model','');
    if ($m === '') {
      $m = (string) get_option('ouinpo_ai_embedding_model','text-embedding-3-large');
    }

    return $m !== '' ? $m : 'text-embedding-3-large';

  }



  // --- Public: réponse avec contexte RAG + mémoire récente ---

  /**

   * Construit les messages à partir du persona, de l'historique récent et du contexte RAG.

   * $rag_chunks: tableau renvoyé par RAG::search(...) ; on le passe via RAG::format_context(...)

   */

  private static function user_agent(string $suffix = 'Fallback'): string {
  $host = wp_parse_url(home_url(), PHP_URL_HOST);
  if (!$host) {
      $host = 'wordpress.local';
  }

  return 'OuInPo-SegFault-' . $suffix . '/1.0 (+' . $host . ')';
  }

  private static function nsi_out_of_program_notice(string $prompt, int $user_id): string {

  if (

    !class_exists('\\OuInPo\\SegFault\\RAG') ||

    !method_exists('\\OuInPo\\SegFault\\RAG', 'current_student_level')

  ) {

    return '';

  }



  $level = \OuInPo\SegFault\RAG::current_student_level($user_id);



  if ($level !== 'premiere') {

    return '';

  }



  $q = strtolower(remove_accents($prompt));

  $q = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $q);

  $q = preg_replace('/\s+/u', ' ', trim((string)$q));



  $terminale_topics = [

    'pile' => 'les piles',

    'piles' => 'les piles',

    'file' => 'les files',

    'files' => 'les files',

    'fifo' => 'les files',

    'lifo' => 'les piles',

    'arbre' => 'les arbres',

    'arbres' => 'les arbres',

    'graphe' => 'les graphes',

    'graphes' => 'les graphes',

    'diviser pour regner' => 'diviser pour régner',

    'programmation dynamique' => 'la programmation dynamique',

  ];



  foreach ($terminale_topics as $needle => $label) {

    if (preg_match('/\b' . preg_quote($needle, '/') . '\b/u', $q)) {

        return "ATTENTION PROGRAMME : l'élève est en Première NSI et la question porte sur {$label}, une notion de Terminale NSI.

        

        Règles obligatoires :

        - Commence la réponse en disant clairement que cette notion n'est pas exigible en Première NSI.

        - Donne seulement une intuition simple, courte, sans entrer dans un cours complet de Terminale.

        - Ne propose aucun exercice sur cette notion.

        - N'affiche aucune section « Exercices conseillés » pour cette notion.

        - Ne recommande aucun lien vers un cours ou exercice de Terminale.

        - Si l'élève demande explicitement un exercice, explique que tu ne proposes pas d'exercice car ce serait hors programme pour son niveau actuel.";

    }

  }



  return '';

}

   

  static function respond_with_context(string $session, string $user_prompt, array $rag_chunks, array $extra_system = []): string {

    $is_logged_in = function_exists('is_user_logged_in') && is_user_logged_in();

    $has_albert =

      class_exists('\\OuInPo\\SegFault\\Albert')

      && ($is_logged_in ? \OuInPo\SegFault\Albert::available() : \OuInPo\SegFault\Albert::public_available());

    

    $has_openai =

      $is_logged_in

      && trim((string) self::api_key()) !== '';

    

    if (!$has_albert && !$has_openai) {

      return "Erreur : aucun moteur IA n’est configuré dans les réglages SegFault.";

    }



    // 1) Persona (si présent)

    $persona = '';

    if (class_exists('\\OuInPo\\SegFault\\Persona') && method_exists('\\OuInPo\\SegFault\\Persona','system')) {

      $persona = (string) \OuInPo\SegFault\Persona::system();

    } else {

      // fallback court

      $persona = "Tu es SegFault, chat NSI ironique mais bienveillant, priorise le contenu du site et des sources privées, réponses brèves, pédagogiques.";

    }



// 2) Contexte pédagogique élève + contexte RAG documentaire

$student_context = '';



if (

  class_exists('\\OuInPo\\SegFault\\RAG')

  && method_exists('\\OuInPo\\SegFault\\RAG', 'student_pedagogical_context')

) {

  $student_context = \OuInPo\SegFault\RAG::student_pedagogical_context(get_current_user_id());

}



$rag_context = \OuInPo\SegFault\RAG::format_context($rag_chunks);



$context = trim(

  "CONTEXTE PÉDAGOGIQUE ÉLÈVE\n"

  . $student_context

  . "\n\nCONTEXTE DOCUMENTAIRE RAG\n"

  . $rag_context

);



    // 3) Historique récent de la session (2-4 derniers tours)

    $recent = [];

    if (class_exists('\\OuInPo\\SegFault\\DB') && method_exists('\\OuInPo\\SegFault\\DB','last_turns')) {

      $recent = \OuInPo\SegFault\DB::last_turns($session, 4); // renvoie [['role'=>'user|assistant','content'=>'...'],...]

    }



    // 4) Messages

    $messages = [];

$configured_rag_prompt = trim((string) get_option('ouinpo_ai_rag_system_prompt', ''));
$configured_guardrails = trim((string) get_option('ouinpo_ai_out_of_program_guardrails', ''));

$system_blocks = array_merge(array_filter([$persona, $configured_rag_prompt, $configured_guardrails]), $extra_system, [

  // Consignes pour lier la requête générique au sujet précédent

  "Si la consigne est générique (\"exercices\", \"propose des idées\"), déduis le sujet à partir des derniers tours et du contexte fourni.",

  "Privilégie le contenu des sources internes. Ne cite des liens que s'ils sont dans le contexte ; sinon, n'en invente pas.",



  // Adaptation pédagogique

  "Utilise le CONTEXTE PÉDAGOGIQUE ÉLÈVE pour adapter la réponse : niveau scolaire, exercices déjà réussis ou tentés, compétences BO fragiles ou acquises.",

  "Ne mentionne jamais le nom, le prénom ou l'identité de l'élève dans ta réponse.",

  "Adresse-toi toujours à l'élève en le tutoyant, jamais en le vouvoyant.",

  "Ne propose pas en priorité un exercice déjà réussi par l'élève.",

  "Si un exercice a déjà été tenté, propose plutôt une aide progressive, une reprise guidée ou un exercice voisin.",

  "Si une compétence BO est fragile, commence par une explication courte et guidée.",

  "Si une compétence BO est acquise, propose une consolidation ou un défi raisonnable.",

  "RÈGLE ABSOLUE — Programme NSI :

Si le contexte pédagogique indique que l'élève est en Première NSI et que la question porte sur une notion de Terminale NSI, tu dois commencer par le signaler clairement.

Tu peux ensuite donner une intuition simple, mais sans faire comme si cette notion était exigible en Première.",



"Repères de programme :

- Première NSI : bases de Python, types simples, booléens, tableaux/listes, dictionnaires, tuples, boucles, fonctions, algorithmique de base, recherche dichotomique, tris simples, données en table, représentation des données, architecture matérielle, web, bases de données selon progression.

- Terminale NSI : récursivité approfondie, piles, files, arbres, graphes, diviser pour régner, programmation dynamique, structures de données abstraites, protocoles de routage, sécurisation, architecture système.",



"Exemples de notions à signaler comme Terminale pour un élève de Première : piles, files, arbres, graphes, récursivité avancée, programmation dynamique, diviser pour régner.",

"N'utilise pas de notion hors programme du niveau indiqué.",

"Quand tu proposes des exercices, utilise uniquement les exercices présents dans le CONTEXTE DOCUMENTAIRE RAG ou dans les sources de type exercise. N'invente pas de titre d'exercice.",

"Ne propose pas un exercice dont le domaine BO ne correspond pas directement à la demande de l'élève.",

"Ne propose pas un exercice d'un niveau supérieur au niveau scolaire indiqué dans le CONTEXTE PÉDAGOGIQUE ÉLÈVE.",

"Si le sujet demandé est hors programme du niveau de l'élève, signale-le clairement puis donne seulement une intuition courte.",

"Si le CONTEXTE PÉDAGOGIQUE ÉLÈVE indique qu'une compétence BO est acquise, ne recommence pas par une explication de base sauf si l'élève le demande explicitement.",

"Pour une compétence déjà acquise, réponds directement par une consolidation, une variante guidée ou un défi raisonnable.",

"Pour une compétence déjà acquise, évite de proposer l'exercice d'introduction ou l'exercice de base correspondant à cette compétence.",

"Quand tu proposes un exercice sur une compétence acquise, formule clairement qu'il s'agit d'une consolidation ou d'un approfondissement.",

"En algorithmique, parle d'étapes, d'itérations ou de comparaisons plutôt que d'éléments parcourus si tu évoques une complexité logarithmique.",

"Si une compétence est indiquée comme acquise dans le CONTEXTE PÉDAGOGIQUE ÉLÈVE, ne donne pas une définition générale de cette compétence en début de réponse.",

"Pour une compétence acquise, commence directement par une phrase de consolidation du type : « Tu as déjà validé cette compétence, on peut donc travailler une variante. »",

"Ne réexplique la définition de base d'une compétence acquise que si l'élève demande explicitement « explique », « je n'ai pas compris » ou « rappelle-moi ».",

"Quand tu proposes des exercices, utilise uniquement les exercices présents dans le CONTEXTE DOCUMENTAIRE RAG ou dans les sources de type exercise.",

"Ne crée jamais un nouveau titre d'exercice.",

"Ne crée jamais un nouvel énoncé d'exercice si aucun exercice pertinent n'est fourni dans le contexte.",

"Si aucun exercice pertinent n'est présent dans le contexte, dis simplement qu'aucun exercice adapté n'a été trouvé dans la banque pour cette demande.",

"Si le niveau de l'élève rend une notion hors programme, ne propose aucun exercice sur cette notion, même si une source ou un cours existe dans le contexte.",

"Si une compétence est indiquée comme acquise, formule explicitement que l'exercice proposé est une consolidation ou un défi, et évite les exercices d'introduction."

]);



$out_of_program_notice = self::nsi_out_of_program_notice($user_prompt, get_current_user_id());



if ($out_of_program_notice !== '') {

  array_unshift($system_blocks, $out_of_program_notice);

}



    $messages[] = ['role'=>'system','content'=>implode("\n\n", array_filter($system_blocks))];



    // on ajoute 2-3 derniers tours (s'ils existent)

    foreach ($recent as $t) {

      $r = ($t['role'] === 'assistant') ? 'assistant' : 'user';

      $c = (string) ($t['content'] ?? '');

      if ($c !== '') $messages[] = ['role'=>$r,'content'=>$c];

    }



    // on ajoute le contexte RAG comme si c'était un “document” fourni

    if ($context !== '') {

      $messages[] = ['role'=>'system', 'content' => "CONTEXT START\n".$context."\nCONTEXT END"];

    }



    // message courant

    $messages[] = ['role'=>'user','content'=>$user_prompt];



    // 5) Appel

    return self::respond($messages);

  }



  // --- Routeur IA principal : Albert d'abord, OpenAI en fallback connecté uniquement ---

  static function respond(array $messages, array $options = []): string {

    $is_logged = function_exists('is_user_logged_in') && is_user_logged_in();

    $albert_available = class_exists('\\OuInPo\\SegFault\\Albert')

      && (

        $is_logged

          ? \OuInPo\SegFault\Albert::available()

          : \OuInPo\SegFault\Albert::public_available()

      );



    if ($albert_available) {

      try {

        $answer = \OuInPo\SegFault\Albert::respond($messages, $options);



        if (!self::is_albert_error_like($answer)) {

          return $answer;

        }



        \Ouinpo\Suite\Core\AiSettings::debug_log('Albert returned an error-like answer', ['provider' => 'albert']);

      } catch (\Throwable $e) {

        \Ouinpo\Suite\Core\AiSettings::debug_log('Albert exception', ['provider' => 'albert', 'error' => $e->getMessage()]);

      }

    }



    // Fallback OpenAI seulement pour les utilisateurs connectés.

    // Les visiteurs anonymes ne doivent pas consommer l'ancien fournisseur.

    if ($is_logged) {

      return self::respond_openai($messages, $options);

    }



    return "L’IA Albert est momentanément indisponible. Réessaie un peu plus tard.";

  }



  private static function is_albert_error_like(string $answer): bool {

    $a = trim($answer);



    if ($a === '') {

      return true;

    }



    $needles = [

      'SegFault public n’est pas encore configuré',

      'SegFault public est momentanément indisponible',

      'SegFault public a reçu une réponse vide',

      'SegFault public est injoignable',

    ];



    foreach ($needles as $needle) {

      if (stripos($a, $needle) !== false) {

        return true;

      }

    }



    return false;

  }



  // --- Ancien moteur OpenAI : conservé comme fallback pour élèves connectés ---

  private static function respond_openai(array $messages, array $options = []): string {

    $apiKey = self::api_key();

    if (!$apiKey) return "Erreur : clé API OpenAI fallback manquante.";



    $payload = [

      'model'       => self::chat_model(),

      'messages'    => $messages,

      'temperature' => array_key_exists('temperature', $options) ? (float) $options['temperature'] : (float) get_option('ouinpo_ai_temperature', 0.3),

      'top_p'       => array_key_exists('top_p', $options) ? (float) $options['top_p'] : (float) get_option('ouinpo_ai_top_p', 1.0),

      'max_tokens'  => array_key_exists('max_tokens', $options) ? (int) $options['max_tokens'] : (int) get_option('ouinpo_ai_max_tokens', 800),

    ];



    if (!empty($options['response_format']) && is_array($options['response_format'])) {

      $payload['response_format'] = $options['response_format'];

    }



    $attempts = 0;

    do {

      $attempts++;



      $resp = wp_remote_post('https://api.openai.com/v1/chat/completions', [

        'headers' => [

          'Authorization' => 'Bearer ' . $apiKey,

          'Content-Type'  => 'application/json',

          'Accept'        => 'application/json',

        ],

        'body'       => wp_json_encode($payload),

        'timeout'    => (int) get_option('ouinpo_ai_timeout', 35),

        'user-agent' => self::user_agent('Fallback'),

      ]);



      if (is_wp_error($resp)) {

        \Ouinpo\Suite\Core\AiSettings::debug_log('OpenAI fallback HTTP error', ['provider' => 'openai', 'error' => $resp->get_error_message()]);

        if ($attempts < 2) usleep(250000);

        continue;

      }



      $code = (int) wp_remote_retrieve_response_code($resp);

      $raw  = wp_remote_retrieve_body($resp);

      $body = json_decode($raw, true);



      if ($code === 429 || ($code >= 500 && $code < 600)) {

        \Ouinpo\Suite\Core\AiSettings::debug_log('OpenAI fallback retry', ['provider' => 'openai', 'http_code' => $code]);

        if ($attempts < 2) {

          usleep(400000);

          continue;

        }

      }



      if ($code !== 200) {

        \Ouinpo\Suite\Core\AiSettings::debug_log('OpenAI fallback non-200', ['provider' => 'openai', 'http_code' => $code]);

        return "Oups, chat perché : l’IA de secours n’a pas répondu correctement.";

      }



      if (!empty($body['choices'][0]['message']['content'])) {

        return trim((string) $body['choices'][0]['message']['content']);

      }



      if (!empty($body['choices'][0]['text'])) {

        return trim((string) $body['choices'][0]['text']);

      }



      if (!empty($body['output_text'])) {

        return trim((string) $body['output_text']);

      }



      if (!empty($body['output'][0]['content'][0]['text'])) {

        return trim((string) $body['output'][0]['content'][0]['text']);

      }



      \Ouinpo\Suite\Core\AiSettings::debug_log('OpenAI fallback parse fail', ['provider' => 'openai']);

      return "Je ronronne dans le vide : réponse vide reçue par l’IA de secours.";

    } while ($attempts < 2);



    return "Je feule : l’IA de secours est injoignable pour le moment.";

  }



  // --- Embeddings inchangé (avec modèle par défaut sûr) ---

  static function embed(string $text): array {

    $apiKey = self::api_key();

    if (!$apiKey) return [];

    $model  = self::embed_model();



    $resp = wp_remote_post('https://api.openai.com/v1/embeddings', [

      'headers'=>[

        'Authorization'=>'Bearer '.$apiKey,

        'Content-Type'=>'application/json',

        'Accept'=>'application/json',

      ],

      'body'=>wp_json_encode(['model'=>$model,'input'=>$text]),

      'timeout'=>(int) get_option('ouinpo_ai_timeout', 35),

      'user-agent' => self::user_agent('Default'),

    ]);



    if (is_wp_error($resp)) {

      \Ouinpo\Suite\Core\AiSettings::debug_log('Embedding HTTP error', ['provider' => 'openai', 'error' => $resp->get_error_message()]);

      return [];

    }

    $code = (int) wp_remote_retrieve_response_code($resp);

    if ($code !== 200) {

      \Ouinpo\Suite\Core\AiSettings::debug_log('Embedding non-200', ['provider' => 'openai', 'http_code' => $code]);

      return [];

    }

    $body = json_decode(wp_remote_retrieve_body($resp), true);

    return $body['data'][0]['embedding'] ?? [];

  }

}

