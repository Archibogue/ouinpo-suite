<?php
declare(strict_types=1);
namespace OuInPo\SegFault {
    final class OpenAI {
        public static int $calls = 0;
        public static array $messages = [];
        public static string $answer = '{"appreciation":["INC-0001 : le résultat est documenté mais le message peut être précisé."],"conseils":["INC-0001 : commence par vérifier les traces."]}';
        public static function respond(array $messages, array $options): string {
            self::$calls++; self::$messages = $messages; return self::$answer;
        }
    }
}
namespace {
    require __DIR__ . '/check-ticket-markdown.php';
    use Ouinpo\Suite\Modules\TicketSimulator\AttemptAdvice;
    use OuInPo\SegFault\OpenAI;
    define('MINUTE_IN_SECONDS', 60); define('DAY_IN_SECONDS', 86400); define('HOUR_IN_SECONDS', 3600);
    $options = ['ouinpo_ai_enabled'=>1]; $transients = [];
    function get_option($key, $default = false) { global $options; return $options[$key] ?? $default; }
    function get_transient($key) { global $transients; return $transients[$key] ?? false; }
    function set_transient($key, $value, $ttl) { global $transients; $transients[$key] = $value; }
    function sanitize_key($key) { return $key; }
    function is_wp_error($value) { return $value instanceof \WP_Error; }
    $current = 11;
    $post = $routes['POST /attempts/(?P<id>\d+)/summary']['callback'];
    $current = 12;
    check(isDenied($post($request)) && OpenAI::$calls === 0, 'Foreign student denied before any AI call');
    $current = 11;
    $feedback = AttemptAdvice::generate($md);
    check(str_contains($feedback, 'INC-0001') && OpenAI::$calls === 1, 'SegFault bridge generates advice');
    check(str_contains($feedback, 'Appréciation IA des réponses libres') && str_contains($feedback, 'Conseils pour progresser'), 'AI free-text evaluation is separated from recommendations');
    $sent = OpenAI::$messages[1]['content'];
    foreach (['SECRET_TEACHER_SOLUTION','SECRET_EXPECTED_CODE','SECRET_PENDING_REPLY','SECRET_EVENT_EXTRA','- Étudiant :','- Tentative :'] as $secret) {
        check(!str_contains($sent, $secret), 'AI context excludes ' . $secret);
    }
    check(AttemptAdvice::generate($md) === $feedback && OpenAI::$calls === 1, 'Unchanged report reuses cached advice');
    AttemptAdvice::generate($md . '\nNouvelle action');
    check(OpenAI::$calls === 2, 'New work regenerates feedback');
    $options['ouinpo_ai_enabled'] = 0;
    check(str_contains(AttemptAdvice::generate($md), 'désactivées') && OpenAI::$calls === 2, 'Disabled AI blocks calls and cached feedback');
    $options['ouinpo_ai_enabled'] = 1;
    OpenAI::$answer = 'Provider error containing private diagnostic';
    check(str_contains(AttemptAdvice::generate($md . 'error'), 'indisponible'), 'Invalid provider response has safe fallback');
    OpenAI::$answer = '{"appreciation":["<img src=x> [click](https://example.org)"],"conseils":["<img src=x> [click](https://example.org)"]}';
    check(str_contains(AttemptAdvice::generate($md . 'unsafe'), '&lt;img') && str_contains(AttemptAdvice::generate($md . 'unsafe'), '\\[click'), 'Model HTML and links are inert');
    check(str_contains(AttemptAdvice::generate(str_repeat('x', 60001)), 'taille analysable'), 'Large reports are not silently truncated');
    $options['ouinpo_ai_student_per_minute'] = 1;
    check(str_contains(AttemptAdvice::generate($md . 'quota'), 'quota IA'), 'Configured quota enforced');
    $response = $post($request);
    check($response instanceof WP_REST_Response && str_contains($response->data['markdown'], 'Action numéro 501') && str_contains($response->data['markdown'], 'Conseils de SegFault'), 'Full report remains downloadable when quota reached');
    $legacy = $routes['GET /attempts/(?P<id>\d+)/summary']['callback'];
    $response = $legacy($request);
    check($response instanceof WP_REST_Response && str_contains($response->data['markdown'], 'Conseils de SegFault'), 'Older pages using GET also receive the feedback section');
    $current = 12;
    check(isDenied($legacy($request)), 'Legacy feedback export rejects foreign students');
    echo "Total: $checks checks passed.\n";
}
