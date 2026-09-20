<?php
/** Local integration harness. Never load this file through HTTP. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== '--bsiotest-local') { exit("Usage: php check-patadesk-wordpress.php --bsiotest-local <WordPress path> <install|test>\n"); }
$localWpPath=realpath($argv[2] ?? '');
if (!$localWpPath || !is_file($localWpPath.'/wp-load.php') || basename(dirname($localWpPath,2))!=='bsiotest') { throw new RuntimeException('Only the explicitly named bsiotest Local installation is allowed.'); }
define('DISABLE_WP_CRON',true);
define('WP_HTTP_BLOCK_EXTERNAL',true);
define('WP_ACCESSIBLE_HOSTS','bsiotest.local,localhost,127.0.0.1');
$_SERVER['HTTP_HOST']='bsiotest.local';$_SERVER['SERVER_NAME']='bsiotest.local';$_SERVER['SERVER_PORT']='80';$_SERVER['REQUEST_METHOD']='GET';
require $localWpPath.'/wp-load.php';
if (parse_url(home_url(),PHP_URL_HOST)!=='bsiotest.local') { throw new RuntimeException('Unexpected site URL.'); }
add_filter('pre_wp_mail',static fn()=>true);
$mode=$argv[3] ?? 'test';
if($mode==='install') {
    require_once ABSPATH.'wp-admin/includes/plugin.php';
    // Backup small existing local site's database before the first activation.
    $backup=[];
    foreach($wpdb->get_col('SHOW TABLES') as $table) {
        if(!str_starts_with($table,$wpdb->prefix))continue;
        $backup[$table]=['schema'=>$wpdb->get_row("SHOW CREATE TABLE `$table`",ARRAY_N)[1],'rows'=>$wpdb->get_results("SELECT * FROM `$table`",ARRAY_A)];
    }
    $backupFile=dirname($localWpPath,2).'/patadesk-before-'.gmdate('Ymd_His').'.json';
    if(file_exists($backupFile) || file_put_contents($backupFile,wp_json_encode($backup),LOCK_EX)===false) { throw new RuntimeException('A private backup must succeed before activation.'); }
    update_option('ouinpo_suite_enabled_modules',['exercises','flashcards','ticket_simulator'],false);
    $result=activate_plugin('ouinpo-suite/ouinpo-suite.php');
    if(is_wp_error($result))throw new RuntimeException($result->get_error_message());
    echo 'Activated local working copy. Backup: '.$backupFile."\n";
    echo 'Schema version: '.get_option('ouinpo_ticket_schema_version','missing')."\n";
    exit;
}

use Ouinpo\Suite\Modules\TicketSimulator\{Assessment,Installer,ScenarioRepository,AssignmentService,AttemptRepository,ScenarioAttempt,PermissionService,Pedagogy,AttemptMarkdown,StudentView,RestController};
use Ouinpo\Suite\Core\Capabilities as Caps;
$checks=0;
function verify(bool $condition,string $message):void {global $checks;if(!$condition)throw new RuntimeException('FAIL: '.$message);$checks++;echo 'OK: '.$message."\n";}
function denied(callable $callback,string $message):void {try{$callback();}catch(Throwable $e){verify(true,$message);return;}verify(false,$message);}
verify(get_option('ouinpo_ticket_schema_version')===Installer::VERSION,'Current schema installed on actual MariaDB');
verify((bool)$wpdb->get_row("SHOW INDEX FROM {$wpdb->prefix}ouinpo_ticket_assignments WHERE Key_name='target_activity'"),'New assignment index exists');
verify(!$wpdb->get_row("SHOW INDEX FROM {$wpdb->prefix}ouinpo_ticket_assignments WHERE Key_name='target'"),'Old restrictive assignment index removed');
foreach(['settings','activity_key'] as $column)verify((bool)$wpdb->get_row("SHOW COLUMNS FROM {$wpdb->prefix}ouinpo_ticket_assignments LIKE '$column'"),'Actual assignment column '.$column);
verify((bool)$wpdb->get_row("SHOW COLUMNS FROM {$wpdb->prefix}ouinpo_ticket_attempts LIKE 'assessment'"),'Actual assessment column');
$run=gmdate('Ymd_His');$accounts=[];
foreach(['teacher','student1','student2','other_teacher'] as $role) {
    $password=wp_generate_password(28,true,true);
    $id=wp_insert_user(['user_login'=>'pdqa_'.$run.'_'.$role,'user_pass'=>$password,'display_name'=>'Recette PataDesk '.$role.' '.$run,'role'=>'subscriber']);
    if(is_wp_error($id))throw new RuntimeException($id->get_error_message());
    $u=new WP_User($id);
    foreach(str_contains($role,'teacher')?[Caps::TICKET_MANAGE,Caps::TICKET_OBSERVE,Caps::MANAGE_CLASSES]:[Caps::TICKET_PRACTICE,Caps::TRACK_LEARNING_DATA] as $cap)$u->add_cap($cap);
    $accounts[$role]=(int)$id;
}
$teacher=$accounts['teacher'];$student=$accounts['student1'];
wp_set_current_user($teacher);
$definition=json_decode(file_get_contents(__DIR__.'/templates/patadesk/SPOPI-qualification-v2.json'),true);
$definition['title']='[RECETTE '.$run.'] Qualification SPOPI';
$scenarios=new ScenarioRepository();$sid=$scenarios->save(0,$definition,'published',0);
$rubric=array_values(Assessment::templates())[0];
$service=new AssignmentService();
$service->save($sid,'user',(string)$student,true,['mode'=>'practice','label'=>'[RECETTE] Entraînement','attempts'=>2]);
$service->save($sid,'user',(string)$student,true,['mode'=>'graded','label'=>'[RECETTE] Évaluation','attempts'=>2,'rubric'=>$rubric]);
$all=$service->listing($sid);$graded=(int)$all[0]['id'];$practice=(int)$all[1]['id'];
verify($graded!==$practice,'Two activities on same scenario and target stored in MariaDB');
$repo=new AttemptRepository();wp_set_current_user($student);
$pid=$repo->start($practice);$aid=$repo->start($graded);
verify($pid!==$aid,'Training and graded attempt isolated');
verify($repo->start($graded)===$aid,'Repeated start preserves current graded attempt');
// Complete a real short exercise while the incident remains technically open.
$tid=$definition['tickets'][0]['id'];
$repo->mutate($pid,$tid,0,'intake',['title'=>'Lien indisponible','requester'=>'Gérard','service'=>'Centre de services','application'=>'Portail SPOPI','symptoms'=>'Le lien ne s’ouvre pas','missing_information'=>'Navigateur et URL','questions'=>'Quelle adresse et quel navigateur ?']);
$repo->mutate($pid,$tid,1,'qualify',['nature'=>'incident','category'=>'Applicatif / Web','impact'=>'Moyen','urgency'=>'Moyenne','priority'=>'Normale','priority_justification'=>'Deux utilisateurs ; contournement téléphonique disponible.']);
$repo->mutate($pid,$tid,2,'evidence',['context'=>'Deux agents du centre de services','information'=>'Navigateur et URL à confirmer','initial_response'=>'Prise en charge sous 4 h ouvrées selon P2 ; prochain point de suivi annoncé.']);
$repo->mutate($pid,$tid,3,'finish_exercise',[]);
verify($repo->get($pid)['status']==='completed' && $repo->states($pid)[$tid]['status']==='new','Actual qualification exercise completes without technical resolution');
$repo->mutate($pid,$tid,4,'action',['action_id'=>'note','message'=>'Note interne de recette']);
$events=(new \Ouinpo\Suite\Modules\TicketSimulator\EventRepository())->listing($pid);
$notes=array_filter($events,fn($e)=>($e['payload']['text']??'')==='Note interne de recette');
verify(count($notes)===1 && reset($notes)['event_type']==='technical_note','Actual internal note persisted without requester message');
denied(fn()=>$repo->start($graded,true),'Cannot restart unsubmitted work');
$a=$repo->get($aid);$ticket=$definition['tickets'][0]['id'];
denied(fn()=>$repo->mutate($aid,$ticket,(int)$a['revision'],'dialogue',[]),'Direct AI operation prohibited by assessment settings');
denied(fn()=>$repo->mutate($aid,$ticket,(int)$a['revision'],'reset_ticket',['confirm_reset'=>true]),'Direct ticket reset prohibited');
wp_set_current_user($accounts['student2']);
denied(fn()=>Assessment::read($aid),'Foreign student cannot read assessment');
denied(fn()=>$repo->mutate($aid,$ticket,(int)$a['revision'],'note',['message'=>'foreign']),'Foreign student cannot mutate work');
wp_set_current_user($student);
Assessment::change($aid,'submit',['revision'=>(int)$a['revision'],'confirm'=>true]);
$a=$repo->get($aid);$frozen=Assessment::data($a)['submissions'][0];
verify(count($frozen['missing'])>0,'Incomplete submission persisted');
verify(!PermissionService::edit($a),'Submitted attempt is read only');
denied(fn()=>$repo->mutate($aid,$ticket,(int)$a['revision'],'note',['message'=>'after']),'Mutation blocked after submission');
wp_set_current_user($teacher);
$input=['criteria'=>[],'general'=>'Retour publié de recette','verified_evidence'=>'Preuves fictives de recette, aucune compétence certifiée.'];
foreach($rubric as $r)$input['criteria'][$r['id']]=['points'=>3,'comment'=>'Commentaire de recette'];
Assessment::change($aid,'grade',['revision'=>(int)$a['revision']]+$input);
wp_set_current_user($student);
verify(!isset(Assessment::read($aid)['result']),'Draft grade hidden from student in real database');
verify(!str_contains(AttemptMarkdown::download($aid)['markdown'],'Retour publié de recette'),'Draft feedback absent from actual Markdown export');
wp_set_current_user($teacher);$a=$repo->get($aid);
Assessment::change($aid,'publish',['revision'=>(int)$a['revision']]);
wp_set_current_user($student);
verify(Assessment::read($aid)['result']['grade']===15,'Published manual grade visible');
verify(str_contains(AttemptMarkdown::download($aid)['markdown'],'Retour publié de recette'),'Published feedback in Markdown');
wp_set_current_user($teacher);$a=$repo->get($aid);
Assessment::change($aid,'reopen',['revision'=>(int)$a['revision'],'reason'=>'Recette : compléter une preuve']);
wp_set_current_user($student);$a=$repo->get($aid);
verify(PermissionService::edit($a),'Reopened work editable');
$repo->mutate($aid,$ticket,(int)$a['revision'],'note',['message'=>'Nouvelle preuve après réouverture']);
$a=$repo->get($aid);Assessment::change($aid,'submit',['revision'=>(int)$a['revision'],'confirm'=>true]);
$d=Assessment::data($repo->get($aid));
verify(count($d['submissions'])===2 && $d['submissions'][0]===$frozen,'Immutable first copy and append-only resubmission on MariaDB');
$next=$repo->start($graded,true);verify($next!==$aid,'Second allowed attempt created');
Assessment::change($next,'submit',['revision'=>0,'confirm'=>true]);
denied(fn()=>$repo->start($graded,true),'Attempt limit enforced on actual database');
wp_set_current_user($teacher);
$rows=Assessment::results(['assignment'=>$graded]);verify(count($rows)===2,'Assignment filter returns two actual copies');
$future=['mode'=>'graded','label'=>'[RECETTE] Ouverture future','rubric'=>$rubric,'opens_at'=>'2099-01-01T00:00:00Z'];
$service->save($sid,'user',(string)$student,true,$future);$futureId=(int)$service->listing($sid)[0]['id'];
$past=['mode'=>'graded','label'=>'[RECETTE] Retard interdit','rubric'=>$rubric,'due_at'=>'2000-01-01T00:00:00Z'];
$service->save($sid,'user',(string)$student,true,$past);$pastId=(int)$service->listing($sid)[0]['id'];
$past['late_policy']='flag';$past['label']='[RECETTE] Retard signalé';
$service->save($sid,'user',(string)$student,true,$past);$lateId=(int)$service->listing($sid)[0]['id'];
wp_set_current_user($student);
denied(fn()=>$repo->start($futureId),'Actual start blocked before opening');
denied(fn()=>$repo->start($pastId),'Actual start blocked after deadline');
$lateAttempt=$repo->start($lateId);Assessment::change($lateAttempt,'submit',['revision'=>0,'confirm'=>true]);
verify(Assessment::data($repo->get($lateAttempt))['submissions'][0]['late']===true,'Actual late submission is flagged');
wp_set_current_user($teacher);
$csv=Assessment::results(['assignment'=>$graded],true);verify(str_contains($csv['csv'],'État'),'CSV generated from actual results');
wp_set_current_user($accounts['other_teacher']);verify(Assessment::results([])===[],'Other teacher sees none of these copies');
// Real WordPress REST dispatch: permissions, nonce and filtered payloads.
do_action('rest_api_init');
wp_set_current_user($student);
function dispatch(string $method,string $path,array $body=[],?string $nonce=null): WP_REST_Response {
    $r=new WP_REST_Request($method,'/'.RestController::NS.$path);
    $r->set_header('X-WP-Nonce',$nonce??wp_create_nonce('wp_rest'));
    if($body){$r->set_header('Content-Type','application/json');$r->set_body(wp_json_encode($body));}
    return rest_do_request($r);
}
verify(dispatch('GET','/attempts/'.$aid,[],'invalid')->get_status()===403,'Invalid nonce rejected by real REST dispatch');
$payload=dispatch('GET','/attempts/'.$aid)->get_data();
verify(!isset($payload['snapshot']) && !isset($payload['tickets'][0]['teacher']),'Actual REST student view excludes private scenario');
verify(dispatch('POST','/attempts/'.$aid.'/assessment/grade',['revision'=>(int)$repo->get($aid)['revision']]+$input)->get_status()===403,'Student cannot grade by direct REST call');
wp_set_current_user($accounts['student2']);verify(dispatch('GET','/attempts/'.$aid)->get_status()===403,'Foreign student denied by actual REST');
wp_set_current_user($teacher);
$page=wp_insert_post(['post_title'=>'Recette PataDesk '.$run,'post_name'=>'recette-patadesk-'.$run,'post_type'=>'page','post_status'=>'publish','post_content'=>'[ouinpo_ticket_simulator]']);
verify(!is_wp_error($page) && $page>0,'Local test shortcode page created');
// HTTP requests travel through Local's Apache/PHP, with genuine WP session cookies.
function httpSession(int $user):array {
    wp_set_current_user($user);$expiry=time()+1800;
    $token=WP_Session_Tokens::get_instance($user)->create($expiry);
    $cookie=wp_generate_auth_cookie($user,$expiry,'logged_in',$token);
    $_COOKIE[LOGGED_IN_COOKIE]=$cookie;
    return ['cookie'=>LOGGED_IN_COOKIE.'='.$cookie,'nonce'=>wp_create_nonce('wp_rest'),'user'=>$user,'token'=>$token];
}
function httpRequest(string $path,array $session,string $method='GET',?array $body=null):array {
    $c=curl_init('http://bsiotest.local'.$path);
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>['Cookie: '.$session['cookie'],'X-WP-Nonce: '.$session['nonce'],'Content-Type: application/json']]);
    if($body!==null)curl_setopt($c,CURLOPT_POSTFIELDS,wp_json_encode($body));
    $raw=curl_exec($c);$status=curl_getinfo($c,CURLINFO_RESPONSE_CODE);$error=curl_error($c);curl_close($c);
    if($raw===false)throw new RuntimeException('HTTP failure: '.$error);
    return ['status'=>$status,'raw'=>$raw,'data'=>json_decode($raw,true)];
}
$session=httpSession($student);$rest='/wp-json/'.RestController::NS;
try {
    $response=httpRequest($rest.'/attempts/'.$aid,$session);
    verify($response['status']===200 && isset($response['data']['assessment']),'Authenticated HTTP REST works through Local Apache');
    verify(!isset($response['data']['snapshot']) && !isset($response['data']['assessment']['history']),'HTTP student payload omits private snapshots and corrections');
    $bad=$session;$bad['nonce']='invalid';verify(httpRequest($rest.'/attempts/'.$aid,$bad)['status']===403,'HTTP invalid nonce denied');
    verify(httpRequest($rest.'/attempts/'.$aid.'/tickets/'.$ticket.'/notes',$session,'POST',['revision'=>(int)$repo->get($aid)['revision'],'message'=>'HTTP blocked'])['status']===403,'HTTP write to submitted copy denied');
    verify(httpRequest($rest.'/attempts/'.$aid.'/summary',$session,'POST',[])['status']===200,'HTTP Markdown export works without AI');
    $html=httpRequest(parse_url(get_permalink($page),PHP_URL_PATH),$session);
    verify($html['status']===200 && str_contains($html['raw'],'data-ticket-student'),'Actual shortcode page renders for student');
    verify(str_contains($html['raw'],'ticket-simulator.js'),'Actual WordPress page includes front-end asset');
    $foreign=httpSession($accounts['student2']);
    try { verify(httpRequest($rest.'/attempts/'.$aid,$foreign)['status']===403,'HTTP foreign student denied'); }
    finally {WP_Session_Tokens::get_instance($foreign['user'])->destroy($foreign['token']);}
} finally {WP_Session_Tokens::get_instance($session['user'])->destroy($session['token']);unset($_COOKIE[LOGGED_IN_COOKIE]);}
// Upgrade actual legacy tables under an isolated test prefix; no live table is downgraded.
$originalPrefix=$wpdb->prefix;$migrationPrefix=$originalPrefix.'pdqa_'.$run.'_';
$oldVersion=get_option('ouinpo_ticket_schema_version');
try {
    foreach(['scenarios','assignments','attempts','attempt_tickets','events'] as $suffix) {
        $schema=$wpdb->get_row('SHOW CREATE TABLE '.$originalPrefix.'ouinpo_ticket_'.$suffix,ARRAY_N)[1];
        $schema=str_replace('`'.$originalPrefix.'ouinpo_ticket_'.$suffix.'`','`'.$migrationPrefix.'ouinpo_ticket_'.$suffix.'`',$schema);
        $schema=preg_replace('/^\s*`(?:settings|activity_key|assessment|drafts)`[^\r\n]*\R/m','',$schema);
        $schema=str_replace('`target_activity` (`scenario_id`,`target_type`,`target_id`,`activity_key`)','`target` (`scenario_id`,`target_type`,`target_id`)',$schema);
        verify($wpdb->query($schema)!==false,'Legacy migration fixture table '.$suffix);
    }
    $wpdb->prefix=$migrationPrefix;
    $wpdb->insert(ScenarioRepository::table('scenarios'),['id'=>1,'owner_id'=>$teacher,'title'=>'Legacy migration fixture','status'=>'published','definition'=>wp_json_encode($definition),'updated_at'=>gmdate('Y-m-d H:i:s')]);
    $wpdb->insert(ScenarioRepository::table('assignments'),['id'=>1,'scenario_id'=>1,'target_type'=>'user','target_id'=>(string)$student,'created_by'=>$teacher,'active'=>1]);
    $wpdb->insert(ScenarioRepository::table('attempts'),['id'=>1,'scenario_id'=>1,'assignment_id'=>1,'student_id'=>$student,'teacher_id'=>$teacher,'snapshot'=>wp_json_encode($definition),'started_at'=>gmdate('Y-m-d H:i:s')]);
    $oldSnapshot=$wpdb->get_var('SELECT snapshot FROM '.ScenarioRepository::table('attempts').' WHERE id=1');
    update_option('ouinpo_ticket_schema_version','1',false);Installer::maybeUpgrade();
    verify(get_option('ouinpo_ticket_schema_version')===Installer::VERSION,'Actual schema upgrade completes');
    verify((bool)$wpdb->get_row('SHOW COLUMNS FROM '.ScenarioRepository::table('attempts')." LIKE 'drafts'"),'Private draft column installed');
    verify($wpdb->get_var('SELECT snapshot FROM '.ScenarioRepository::table('attempts').' WHERE id=1')===$oldSnapshot,'Legacy snapshot unchanged after SQL migration');
    $legacy=$wpdb->get_row('SELECT * FROM '.ScenarioRepository::table('assignments').' WHERE id=1',ARRAY_A);
    verify($legacy['activity_key']==='' && Assessment::settings($legacy)['mode']==='practice','Legacy assignment stays training');
    verify($wpdb->insert(ScenarioRepository::table('assignments'),['scenario_id'=>1,'target_type'=>'user','target_id'=>(string)$student,'created_by'=>$teacher,'active'=>1,'activity_key'=>wp_generate_uuid4()])!==false,'Migrated index accepts separate activity for same target');
    Installer::maybeUpgrade();verify(get_option('ouinpo_ticket_schema_version')===Installer::VERSION,'Migration is idempotent');
} finally {
    $wpdb->prefix=$originalPrefix;update_option('ouinpo_ticket_schema_version',$oldVersion,false);
    // Only the five tables created above by this run are disposable fixtures.
    foreach(['events','attempt_tickets','attempts','assignments','scenarios'] as $suffix) {
        $table=$migrationPrefix.'ouinpo_ticket_'.$suffix;
        if(!preg_match('/^[a-zA-Z0-9_]+$/D',$table) || !str_starts_with($table,$originalPrefix.'pdqa_'.$run.'_'))throw new RuntimeException('Unsafe fixture cleanup target.');
        $wpdb->query('DROP TABLE IF EXISTS `'.$table.'`');
    }
}
echo 'RESULT '.wp_json_encode(['checks'=>$checks,'run'=>$run,'scenario'=>$sid,'practice_assignment'=>$practice,'graded_assignment'=>$graded,'practice_attempt'=>$pid,'graded_attempt'=>$aid,'accounts'=>$accounts,'page'=>get_permalink($page)],JSON_UNESCAPED_SLASHES)."\n";
