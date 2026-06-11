<?php

namespace OuInPo\SegFault;



if (!defined('ABSPATH')) exit;



class Albert {

  static function enabled(): bool {
    if (!\Ouinpo\Suite\Core\AiSettings::enabled_for_usage('chat_rag')) {
      return false;
    }

    /*

     * Albert activé comme moteur principal.

     *

     * Compatibilité :

     * si la nouvelle option n’existe pas encore, on reprend l’ancienne

     * option publique pour ne pas casser le comportement actuel.

     */

    $value = get_option('ouinpo_sf_albert_enabled', null);



    if ($value === null) {

      $value = get_option('ouinpo_sf_public_albert_enabled', 0);

    }



    return (int) $value === 1;

  }



  static function public_enabled(): bool {

    /*

     * Accès Albert autorisé pour les visiteurs non connectés.

     * Il faut à la fois :

     * - Albert activé globalement ;

     * - l’accès public explicitement autorisé.

     */

    return \Ouinpo\Suite\Core\AiSettings::public_enabled()
      && self::enabled()

      && (int) get_option('ouinpo_sf_public_albert_enabled', 0) === 1;

  }



  static function available(): bool {

    return self::enabled() && self::api_key() !== '';

  }



  static function public_available(): bool {

    return self::public_enabled() && self::api_key() !== '';

  }



  static function api_key(): string {

    $key = trim((string) get_option('ouinpo_sf_albert_api_key', ''));
    if ($key === '') {
      $key = trim((string) get_option('ouinpo_ai_api_key', ''));
    }

    return $key;

  }

  private static function user_agent(string $suffix = 'RAG'): string {
      $host = wp_parse_url(home_url(), PHP_URL_HOST);
      if (!$host) {
          $host = 'wordpress.local';
      }

      return 'OuInPo-SegFault-' . $suffix . '/1.0 (+' . $host . ')';
  }

  static function base_url(): string {

    $url = trim((string) get_option('ouinpo_sf_albert_base_url', ''));
    if ($url === '') {
      $url = trim((string) get_option('ouinpo_ai_api_base_url', 'https://albert.api.etalab.gouv.fr/v1'));
    }

    $url = rtrim($url, '/');

    return $url !== '' ? $url : 'https://albert.api.etalab.gouv.fr/v1';

  }



  static function chat_model(): string {

    $m = trim((string) get_option('ouinpo_sf_albert_model', ''));
    if ($m === '') {
      $m = trim((string) get_option('ouinpo_ai_chat_model', 'openai/gpt-oss-120b'));
    }

    return $m !== '' ? $m : 'openai/gpt-oss-120b';

  }



static function code_model(): string {

  $m = trim((string) get_option('ouinpo_sf_albert_code_model', ''));
  if ($m === '') {
    $m = trim((string) get_option('ouinpo_ai_code_model', 'openweight-code'));
  }



  if ($m !== '') {

    return $m;

  }



  return self::chat_model();

}



    static function embedding_model(): string {

      $m = trim((string) get_option('ouinpo_sf_albert_embedding_model', ''));
      if ($m === '') {
        $m = trim((string) get_option('ouinpo_ai_embedding_model', 'BAAI/bge-m3'));
      }

      return $m !== '' ? $m : 'BAAI/bge-m3';

    }

    

    static function reranker_model(): string {

      $m = trim((string) get_option('ouinpo_sf_albert_reranker_model', 'BAAI/bge-reranker-v2-m3'));

      return $m !== '' ? $m : 'BAAI/bge-reranker-v2-m3';

    }



    static function ocr_model(): string {

      $m = trim((string) get_option('ouinpo_sf_albert_ocr_model', ''));
      if ($m === '') {
        $m = trim((string) get_option('ouinpo_ai_ocr_model', ''));
      }

      return $m;

    }



static function ocr_document(string $document_url, array $options = []): array|\WP_Error {

  $api_key = self::api_key();

  if ($api_key === '') {

    return new \WP_Error('albert_ocr_key_missing', 'Cle Albert API manquante pour l OCR.');

  }



  $document_url = esc_url_raw(trim($document_url));

  if ($document_url === '') {

    return new \WP_Error('albert_ocr_missing_url', 'URL du document OCR manquante.');

  }



  $payload = [

    'document' => [

      'type' => 'document_url',

      'document_url' => $document_url,

    ],

  ];



  $model = self::ocr_model();

  if ($model !== '') {

    $payload['model'] = $model;

  }



  foreach (['pages', 'image_limit', 'image_min_size', 'include_image_base64', 'document_annotation_format'] as $key) {

    if (array_key_exists($key, $options)) {

      $payload[$key] = $options[$key];

    }

  }



  $resp = wp_remote_post(self::base_url() . '/ocr', [

    'headers' => [

      'Authorization' => 'Bearer ' . $api_key,

      'Content-Type'  => 'application/json',

      'Accept'        => 'application/json',

    ],

    'body'       => wp_json_encode($payload),

    'timeout'    => max(60, (int) get_option('ouinpo_ai_timeout', 45)),

    'user-agent' => self::user_agent('OCR'),

  ]);



  if (is_wp_error($resp)) {

    \Ouinpo\Suite\Core\AiSettings::debug_log('Albert OCR HTTP error', ['provider' => 'albert', 'error' => $resp->get_error_message()]);

    return $resp;

  }



  $code = (int) wp_remote_retrieve_response_code($resp);

  $raw  = (string) wp_remote_retrieve_body($resp);

  $body = json_decode($raw, true);



  if ($code < 200 || $code >= 300) {

    \Ouinpo\Suite\Core\AiSettings::debug_log('Albert OCR non-200', ['provider' => 'albert', 'http_code' => $code]);

    return new \WP_Error('albert_ocr_http_error', 'OCR Albert indisponible pour ce PDF.');

  }



  if (!is_array($body)) {

    \Ouinpo\Suite\Core\AiSettings::debug_log('Albert OCR unexpected response', ['provider' => 'albert']);

    return new \WP_Error('albert_ocr_invalid_response', 'Reponse OCR Albert invalide.');

  }



  return $body;

}



static function embed(string $text): array {

  $api_key = self::api_key();

  if ($api_key === '') {

    \Ouinpo\Suite\Core\AiSettings::debug_log('Albert embeddings API key missing', ['provider' => 'albert']);

    return [];

  }



  $text = trim($text);

  if ($text === '') return [];



  $payload = [

    'model' => self::embedding_model(),

    'input' => $text,

  ];



  $resp = wp_remote_post(self::base_url() . '/embeddings', [

    'headers' => [

      'Authorization' => 'Bearer ' . $api_key,

      'Content-Type'  => 'application/json',

      'Accept'        => 'application/json',

    ],

    'body'       => wp_json_encode($payload),

    'timeout'    => (int) get_option('ouinpo_ai_timeout', 45),

    'user-agent' => self::user_agent('RAG'),

  ]);



  if (is_wp_error($resp)) {

    \Ouinpo\Suite\Core\AiSettings::debug_log('Albert embeddings HTTP error', ['provider' => 'albert', 'error' => $resp->get_error_message()]);

    return [];

  }



  $code = (int) wp_remote_retrieve_response_code($resp);

  $raw  = (string) wp_remote_retrieve_body($resp);

  $body = json_decode($raw, true);



  if ($code < 200 || $code >= 300) {

    \Ouinpo\Suite\Core\AiSettings::debug_log('Albert embeddings non-200', ['provider' => 'albert', 'http_code' => $code]);

    return [];

  }



  $emb = $body['data'][0]['embedding'] ?? null;

  if (!is_array($emb) || !$emb) {

    \Ouinpo\Suite\Core\AiSettings::debug_log('Albert embeddings unexpected response', ['provider' => 'albert']);

    return [];

  }



  return array_map('floatval', $emb);

}



static function rerank(string $query, array $documents, int $top_n = 6): array {

  $api_key = self::api_key();

  if ($api_key === '') {

    \Ouinpo\Suite\Core\AiSettings::debug_log('Albert rerank API key missing', ['provider' => 'albert']);

    return $documents;

  }



  $query = trim($query);

  if ($query === '' || empty($documents)) {

    return $documents;

  }



  $top_n = max(1, min(20, $top_n));



  $texts = [];

  foreach ($documents as $i => $doc) {

    $title = trim((string)($doc['title'] ?? ''));

    $chunk = trim((string)($doc['chunk'] ?? ''));



    if ($chunk === '') continue;



    $texts[] = [

      'index' => $i,

      'text'  => ($title !== '' ? "Titre : {$title}\n" : '') . $chunk,

    ];

  }



  if (!$texts) return $documents;



  $payload = [

    'model'     => self::reranker_model(),

    'query'     => $query,

    'documents' => array_map(fn($x) => $x['text'], $texts),

    'top_n'     => $top_n,

  ];



  $resp = wp_remote_post(self::base_url() . '/rerank', [

    'headers' => [

      'Authorization' => 'Bearer ' . $api_key,

      'Content-Type'  => 'application/json',

      'Accept'        => 'application/json',

    ],

    'body'       => wp_json_encode($payload),

    'timeout'    => (int) get_option('ouinpo_ai_timeout', 45),

    'user-agent' => self::user_agent('RAG'),

  ]);



  if (is_wp_error($resp)) {

    \Ouinpo\Suite\Core\AiSettings::debug_log('Albert rerank HTTP error', ['provider' => 'albert', 'error' => $resp->get_error_message()]);

    return $documents;

  }



  $code = (int) wp_remote_retrieve_response_code($resp);

  $raw  = (string) wp_remote_retrieve_body($resp);

  $body = json_decode($raw, true);



  if ($code < 200 || $code >= 300) {

    \Ouinpo\Suite\Core\AiSettings::debug_log('Albert rerank non-200', ['provider' => 'albert', 'http_code' => $code]);

    return $documents;

  }



  $results = $body['results'] ?? $body['data'] ?? null;

  if (!is_array($results)) {

    \Ouinpo\Suite\Core\AiSettings::debug_log('Albert rerank unexpected response', ['provider' => 'albert']);

    return $documents;

  }



  $ranked = [];



  foreach ($results as $r) {

    $local_idx = null;



    if (isset($r['index'])) {

      $local_idx = (int)$r['index'];

    } elseif (isset($r['document']['index'])) {

      $local_idx = (int)$r['document']['index'];

    }



    if ($local_idx === null || !isset($texts[$local_idx])) {

      continue;

    }



    $original_index = (int)$texts[$local_idx]['index'];

    if (!isset($documents[$original_index])) {

      continue;

    }



    $doc = $documents[$original_index];

    $doc['rerank_score'] = isset($r['relevance_score'])

      ? (float)$r['relevance_score']

      : (isset($r['score']) ? (float)$r['score'] : null);



    $ranked[] = $doc;

  }



  return $ranked ?: $documents;

}



  static function respond(array $messages, array $options = []): string {

    $api_key = self::api_key();

    if ($api_key === '') {

      return "SegFault public n’est pas encore configuré : clé Albert API manquante.";

    }



    $max_tokens = (int) get_option('ouinpo_ai_max_tokens', 700);

    if (array_key_exists('max_completion_tokens', $options)) {

      $max_tokens = (int) $options['max_completion_tokens'];

    } elseif (array_key_exists('max_tokens', $options)) {

      // Compatibilité avec le client OpenAI interne : même nom d’option côté appelant.

      $max_tokens = (int) $options['max_tokens'];

    }



    $model = self::chat_model();

    $purpose = 'chat';

    

    if (!empty($options['albert_purpose'])) {

      $purpose = strtolower(trim((string) $options['albert_purpose']));

    

      if ($purpose === 'code') {

        $model = self::code_model();

      }

    }

    

    if (!empty($options['albert_model'])) {

      $override_model = trim((string) $options['albert_model']);

    

      if ($override_model !== '') {

        $model = $override_model;

      }

    }

    

    if (!isset($purpose)) {

      $purpose = 'chat';

    }

    

    $payload = [

      'model' => $model,

      'messages' => $messages,

      'temperature' => array_key_exists('temperature', $options) ? (float) $options['temperature'] : (float) get_option('ouinpo_ai_temperature', 0.3),

      'max_completion_tokens' => $max_tokens,

    ];



    if (array_key_exists('top_p', $options)) {

      $payload['top_p'] = (float) $options['top_p'];
    } else {
      $payload['top_p'] = (float) get_option('ouinpo_ai_top_p', 1.0);

    }



    if (!empty($options['response_format']) && is_array($options['response_format'])) {

      $payload['response_format'] = $options['response_format'];

    }



    $attempts = 0;

    do {

      $attempts++;

      $resp = wp_remote_post(self::base_url() . '/chat/completions', [

        'headers' => [

          'Authorization' => 'Bearer ' . $api_key,

          'Content-Type'  => 'application/json',

          'Accept'        => 'application/json',

        ],

        'body'       => wp_json_encode($payload),

        'timeout'    => (int) get_option('ouinpo_ai_timeout', 45),

        'user-agent' => self::user_agent('Public'),

      ]);



      if (is_wp_error($resp)) {

        if ($attempts < 2) usleep(300000);

        continue;

      }



      $code = (int) wp_remote_retrieve_response_code($resp);

      $raw  = (string) wp_remote_retrieve_body($resp);

      $body = json_decode($raw, true);



      if ($code === 429 || $code === 503 || ($code >= 500 && $code < 600)) {

        \Ouinpo\Suite\Core\AiSettings::debug_log('Albert retry', ['provider' => 'albert', 'http_code' => $code]);

        if ($attempts < 2) { usleep(500000); continue; }

      }



      if ($code !== 200) {

        \Ouinpo\Suite\Core\AiSettings::debug_log('Albert non-200', ['provider' => 'albert', 'http_code' => $code]);

        return "SegFault public est momentanément indisponible.";

      }



      if (!empty($body['choices'][0]['message']['content'])) {

        return trim((string) $body['choices'][0]['message']['content']);

      }



      \Ouinpo\Suite\Core\AiSettings::debug_log('Albert parse fail', ['provider' => 'albert']);

      return "SegFault public a reçu une réponse vide.";

    } while ($attempts < 2);



    return "SegFault public est injoignable pour le moment.";

  }

}

