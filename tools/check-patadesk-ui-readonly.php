<?php
/** Read-only verification on an existing LOCAL fixture. Never creates users, submissions or grades.
 * php tools/check-patadesk-ui-readonly.php /path/to/wordpress
 */
declare(strict_types=1);
if(PHP_SAPI!=='cli'||empty($argv[1])){exit("CLI: provide the local WordPress root.\n");}
define('DISABLE_WP_CRON',true);
require rtrim($argv[1],'/\\').'/wp-load.php';
use Ouinpo\Suite\Modules\TicketSimulator\{Assessment,AttemptRepository,Presentation,ScenarioRepository};
function ui_check(bool $ok,string $label):void {if(!$ok)throw new RuntimeException($label);echo "OK: $label\n";}
global $wpdb;
$tables=['scenarios','assignments','attempts','attempt_tickets','events'];
$digest=static function()use($wpdb,$tables){$hash=hash_init('sha256');foreach($tables as $suffix){$rows=$wpdb->get_results('SELECT * FROM '.ScenarioRepository::table($suffix).' ORDER BY id',ARRAY_A);hash_update($hash,wp_json_encode($rows));}return hash_final($hash);};
$before=$digest();echo "PataDesk data SHA256: $before\n";
$rows=$wpdb->get_results('SELECT * FROM '.ScenarioRepository::table('attempts').' ORDER BY id DESC',ARRAY_A);
$fixture=null;
foreach($rows as $row){$data=Assessment::data($row);$scenario=json_decode($row['snapshot'],true);if(str_starts_with($scenario['title']??'','[RECETTE ')&&!empty($data['submissions'])){$fixture=$row;break;}}
if(!$fixture)exit("No existing submitted RECETTE fixture; no data created.\n");
$teacher=(int)$fixture['teacher_id'];$student=(int)$fixture['student_id'];$id=(int)$fixture['id'];
wp_set_current_user($teacher);
$teacherCopy=Assessment::read($id);
ui_check(!empty($teacherCopy['submissions'][0]['markdown_html']),'Authorized teacher receives rendered retained copy');
$results=Assessment::results([]);
ui_check(count($results)>0&&!empty($results[0]['student_name'])&&!empty($results[0]['scenario_title']),'Authorized results have nominative display metadata');
$csv=Assessment::results([],true)['csv'];
ui_check(count(str_getcsv(explode("\n",$csv)[1],';','"',''))===6,'Existing CSV remains six columns');
ui_check(Assessment::results(['search'=>'RECETTE_NAME_THAT_DOES_NOT_EXIST'])===[],'Nominative empty search is empty');
$settingsBefore=$fixture['assessment'];
wp_set_current_user($student);
$public=Assessment::read($id);
ui_check(!isset($public['history'])&&!isset($public['draft'])&&!str_contains(wp_json_encode($public),'markdown_html')&&!str_contains(wp_json_encode($public),'expected_solution'),'Actual student account cannot read teacher history, draft or retained teacher HTML');
foreach($public['rubric']??[] as $criterion)ui_check(!isset($criterion['private']),'Actual student rubric excludes private instructions');
$listing=(new AttemptRepository())->listing();
ui_check(count($listing)>0,'Actual student can list own attempts');
foreach($listing as $item)ui_check((int)$item['student_id']===$student&&!isset($item['snapshot'])&&!isset($item['assessment']),'Student list is scoped and excludes private source payloads');
$foreign=null;foreach($rows as $row){if((int)$row['student_id']!==$student){$foreign=(int)$row['id'];break;}}
if($foreign){$denied=false;try{Assessment::read($foreign);}catch(Throwable $e){$denied=true;}ui_check($denied,'Actual student cannot read another student assessment');}
$html=Presentation::markdown("<script>alert(1)</script>\n\n[bad](javascript:alert(1))\n\n![pixel](https://example.invalid/pixel.png)");
ui_check(!preg_match('/<(script|img)\b|href="javascript:/i',$html),'Real WordPress sanitizer plus bundled parser rejects active markup');
ui_check($before===$digest(),'Scenarios, assignments, attempts, notes, copies and grades unchanged');
echo "Read-only WordPress checks complete. No browser student session was created.\n";
