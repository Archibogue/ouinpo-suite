<?php
declare(strict_types=1);
require __DIR__ . '/check-ticket-simulator.php';

use Ouinpo\Suite\Modules\TicketSimulator\AttemptMarkdown;

$private = $scenario;
$private['tickets'][0]['expected_solution'] = 'SECRET_TEACHER_SOLUTION';
$private['resources'][2]['expected_content'] = 'SECRET_EXPECTED_CODE';
$private['tickets'][0]['actions'][] = ['id'=>'future_secret','label'=>'SECRET_FUTURE_ACTION','type'=>'test','requires'=>['never_done']];
$reportAttempt = $attempt;
$reportAttempt['snapshot'] = json_encode($private);
$reportState = $initial;
$reportState['visible_resources'][] = 'code';
$reportState['code_edits']['code'] = "<?php\n// code élève\n```\n<script>example</script>";
$reportState['pending'] = ['text'=>'SECRET_PENDING_REPLY'];
$reportState['score'] = 987654;
$reportState['resolution'] = ['cause'=>'Migration identifiée','solution'=>'Correction appliquée','tests'=>'Export mensuel','result'=>'128 lignes','message'=>'Votre export fonctionne.'];
$events = [['id'=>1,'ticket_key'=>'INC-0001','created_at'=>'2026-09-16 10:00:00','event_type'=>'technical_note','payload'=>['text'=>'Ma note de diagnostic','private'=>'SECRET_EVENT_EXTRA']]];
$md = AttemptMarkdown::render($reportAttempt, ['INC-0001'=>$reportState], $events);
check(str_contains($md, 'Ma note de diagnostic'), 'Markdown includes saved technical notes');
check(str_contains($md, 'Migration identifiée') && str_contains($md, 'Votre export fonctionne.'), 'Markdown includes resolution and final message');
check(str_contains($md, '// code élève'), 'Markdown contains student code');
check(str_contains($md, '````text'), 'Embedded code fences cannot escape the Markdown code block');
foreach (['SECRET_TEACHER_SOLUTION','SECRET_EXPECTED_CODE','SECRET_PENDING_REPLY','SECRET_EVENT_EXTRA','SECRET_FUTURE_ACTION','987654'] as $secret) {
    check(!str_contains($md, $secret), 'Markdown excludes ' . $secret);
}
check(str_contains($md, 'Possibilités dans PataDesk') && str_contains($md, 'aucun terminal système'), 'Report provides simulation limitations to AI');
$publicView = \Ouinpo\Suite\Modules\TicketSimulator\StudentView::build($reportAttempt, ['INC-0001'=>$reportState], false);
check(str_contains($md, $publicView['tickets'][0]['actions'][0]['label']), 'Report includes a currently available action label');
$pendingState = $reportState;
$pendingState['pending'] = ['text'=>'SECRET_PENDING_REPLY'];
check(str_contains(AttemptMarkdown::render($reportAttempt, ['INC-0001'=>$pendingState], []), 'Recevoir la réponse et reprendre'), 'Waiting state exposes only the simulated reply action');
$reportAttempt['status'] = 'archived';
check(str_contains(AttemptMarkdown::render($reportAttempt, ['INC-0001'=>$initial], []), 'Archivée'), 'Archived attempt has a report');
check(str_contains(AttemptMarkdown::render($reportAttempt, ['INC-0001'=>$initial], []), 'Aucune résolution renseignée.'), 'Unfinished attempt exports without inventing resolution');
$reportAttempt['snapshot'] = json_encode(array_merge($private, ['title'=>'<img src=x> [lien](https://example.org)']));
check(str_contains(AttemptMarkdown::render($reportAttempt, ['INC-0001'=>$initial], []), '&lt;img'), 'Scenario title is escaped');

final class MarkdownDb
{
    public string $prefix = 'custom_';
    public array $cursors = [];
    public function __construct(private array $attempt, private array $state) {}
    public function prepare($sql, ...$args) { foreach ($args as $arg) { $sql = preg_replace('/%d/', (string)(int)$arg, $sql, 1); } return $sql; }
    public function query($sql) { if (!in_array($sql, ['START TRANSACTION','COMMIT','ROLLBACK'])) { throw new LogicException('Report must not write.'); } return 0; }
    public function get_row($sql, $format) { return $this->attempt; }
    public function get_results($sql, $format) {
        if (str_contains($sql, 'attempt_tickets')) { return [['ticket_key'=>'INC-0001','state'=>json_encode($this->state)]]; }
        preg_match('/AND id>(\d+)/', $sql, $m); $after = (int)$m[1]; $this->cursors[] = $after;
        $rows = [];
        for ($i=$after+1; $i<=min($after+500, 501); $i++) {
            $rows[] = ['id'=>$i,'ticket_key'=>'INC-0001','created_at'=>'2026-09-16 10:00:00','event_type'=>'action','payload'=>json_encode(['text'=>'Action numéro ' . $i])];
        }
        return $rows;
    }
}
$wpdb = new MarkdownDb($reportAttempt, $initial);
$route = $routes['GET /attempts/(?P<id>\d+)/summary/plain']['callback'];
$request = new WP_REST_Request(['id'=>1]);
$current = 12;
check(isDenied($route($request)), 'Another student cannot export the attempt');
$current = 21;
check(isDenied($route($request)), 'Unrelated teacher cannot export the attempt');
$current = 11;
$response = $route($request);
check($response instanceof WP_REST_Response, 'Owner can export archived attempt');
check($response->data['filename'] === 'patadesk-tentative-1.md', 'Download filename is safe and ends in md');
check(str_contains($response->data['markdown'], 'Action numéro 501'), 'Export includes events beyond first page');
check($wpdb->cursors === [0,500], 'Complete history is paginated server-side');
$current = 20;
check($route($request) instanceof WP_REST_Response, 'Authorized observer can download student report');
echo "\n$checks checks passed including Markdown export.\n";
