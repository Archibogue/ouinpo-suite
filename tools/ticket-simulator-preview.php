<?php
/** Isolated UI fixture; NOT a WordPress integration test. Run with PHP's loopback development server. */
if (PHP_SAPI !== 'cli-server' || !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1','::1'], true)) { http_response_code(404); exit; }
define('ABSPATH', __DIR__ . '/');
require dirname(__DIR__) . '/src/Core/Autoloader.php';
Ouinpo\Suite\Core\Autoloader::init(dirname(__DIR__) . '/src');
use Ouinpo\Suite\Modules\TicketSimulator\{ScenarioValidator,ScenarioAttempt,SimulationEngine,StudentView,TicketScenario,TicketResource,CodeWorkspace,SimulatedTestEngine};
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$allowed = ['/assets/js/front/ticket-simulator.js','/assets/js/admin/ticket-simulator-admin.js','/assets/css/front/ticket-simulator.css','/assets/css/admin/ticket-simulator-admin.css'];
if (in_array($path, $allowed, true)) { return false; }
$sessions = sys_get_temp_dir() . '/ouinpo-ticket-preview';
if (!is_dir($sessions)) { mkdir($sessions, 0700, true); }
session_save_path($sessions);
session_name('ouinpo_ticket_preview');
session_start();
$demo = require dirname(__DIR__) . '/src/Modules/TicketSimulator/demo.php';
$_SESSION['scenario'] ??= ['id'=>1,'owner_id'=>20,'status'=>'published','revision'=>1,'definition'=>$demo];
function attemptView(): array {
    $view = StudentView::build($_SESSION['attempt'], $_SESSION['states'], ($_SESSION['mode'] ?? '') === 'admin');
    $view['events'] = $_SESSION['events']; return $view;
}
if (str_starts_with($path, '/api')) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $p = substr($path, 4); $method = $_SERVER['REQUEST_METHOD']; $input = json_decode(file_get_contents('php://input'), true) ?: []; $result = [];
        if ($p === '/demo') { $result = $demo; }
        elseif ($p === '/targets') { $result = [['type'=>'user','id'=>'11','label'=>'Élève de démonstration (#11)']]; }
        elseif ($p === '/scenarios' && $method === 'GET') { $result = [array_diff_key($_SESSION['scenario'], ['definition'=>true])]; }
        elseif (($p === '/scenarios' || $p === '/scenarios/1') && $method !== 'GET') {
            ScenarioValidator::validate($input['definition']); $_SESSION['scenario'] = ['id'=>1,'owner_id'=>20,'revision'=>2,'status'=>$input['status'],'definition'=>$input['definition']]; $result = $_SESSION['scenario'];
        }
        elseif ($p === '/scenarios/1') { $result = $_SESSION['scenario']; }
        elseif ($p === '/scenarios/1/assignments') { $result = $method === 'GET' ? [['id'=>1,'target_type'=>'user','target_id'=>'11','active'=>1]] : ['ok'=>true]; }
        elseif ($p === '/assignments') { $result = [['id'=>1,'scenario_id'=>1,'title'=>$_SESSION['scenario']['definition']['title']]]; }
        elseif ($p === '/assignments/1/attempts') {
            if (!isset($_SESSION['attempt'])) {
                $s = $_SESSION['scenario']['definition']; $_SESSION['attempt'] = ['id'=>1,'scenario_id'=>1,'student_id'=>11,'status'=>'active','revision'=>0,'snapshot'=>json_encode($s),'started_at'=>gmdate('Y-m-d H:i:s'),'ended_at'=>null];
                $_SESSION['states']=[];$_SESSION['events']=[];
                foreach($s['tickets'] as $t){$_SESSION['states'][$t['id']]=ScenarioAttempt::initial($t);}
            }
            $result=['id'=>1];
        }
        elseif ($p === '/attempts') { $result = isset($_SESSION['attempt']) ? [array_diff_key($_SESSION['attempt'], ['snapshot'=>true])] : []; }
        elseif ($p === '/attempts/1') { $result=attemptView(); }
        elseif ($p === '/attempts/1/summary') { $result=['filename'=>'patadesk-tentative-1.md','markdown'=>\Ouinpo\Suite\Modules\TicketSimulator\AttemptMarkdown::render($_SESSION['attempt'], $_SESSION['states'], $_SESSION['events'])]; }
        elseif ($p === '/attempts/1/events') { $result=[]; }
        elseif (preg_match('~^/attempts/1/tickets/([\w-]+)(?:/(actions|resources|notes)(?:/([\w-]+))?)?(/code)?$~',$p,$m)) {
            if (($input['revision']??-1)!==$_SESSION['attempt']['revision']) { throw new RuntimeException('Révision périmée.'); }
            $tid=$m[1];$kind=$m[2]??'';$item=$m[3]??'';$s=json_decode($_SESSION['attempt']['snapshot'],true);$t=TicketScenario::index($s['tickets'])[$tid];$state=$_SESSION['states'][$tid];$events=[];
            if ($kind==='actions') {[$state,$events]=(new SimulationEngine(new SimulatedTestEngine($s['resources'])))->perform($t,$state,$item,$input);}
            elseif (!empty($m[4])) {[$state,$events]=CodeWorkspace::save($t,$state,TicketScenario::index($s['resources'])[$item],$input['content']);}
            elseif ($kind==='resources') {if(!in_array($item,$state['visible_resources'],true)){throw new RuntimeException('Ressource verrouillée.');}$events=[['type'=>'resource','text'=>'Consultation : '.$item]];}
            elseif ($kind==='notes') {$events=[['type'=>'technical_note','text'=>$input['message']]];}
            else {unset($input['revision']);$state['fields']=array_merge($state['fields'],$input);$events=[['type'=>'qualification','text'=>'Qualification mise à jour.']];}
            $_SESSION['states'][$tid]=$state;$_SESSION['attempt']['revision']++;
            $finished=\Ouinpo\Suite\Modules\TicketSimulator\Pedagogy::finished($s, $_SESSION['states']);
            $_SESSION['attempt']['status']=$finished?'completed':'active';
            $_SESSION['attempt']['ended_at']=$finished?($_SESSION['attempt']['ended_at']?:gmdate('Y-m-d H:i:s')):null;
            foreach($events as $e){$_SESSION['events'][]=['id'=>count($_SESSION['events'])+1,'ticket_key'=>$tid,'event_type'=>$e['type'],'created_at'=>gmdate('Y-m-d H:i:s'),'payload'=>$e];}
            $result=$kind==='resources' && empty($m[4])?['resource'=>CodeWorkspace::resource(TicketScenario::index($s['resources'])[$item],$state),'attempt'=>attemptView()]:attemptView();
        } else { http_response_code(404); $result=['message'=>'Route de fixture non disponible.']; }
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    } catch(Throwable $e) {http_response_code(400);echo json_encode(['message'=>$e->getMessage()]);}
    exit;
}
if ($path !== '/') {http_response_code(404);exit;}
$_SESSION['mode'] = isset($_GET['admin']) ? 'admin' : 'student';
$admin=$_SESSION['mode']==='admin';
?><!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>PataDesk — recette isolée</title><link rel="stylesheet" href="/assets/css/front/ticket-simulator.css"><?php if($admin):?><link rel="stylesheet" href="/assets/css/admin/ticket-simulator-admin.css"><?php endif;?></head>
<body<?php if(isset($_GET['mobile'])):?> style="max-width:390px;margin:auto"<?php endif;?>>
<div class="ouinpo-ticketing" <?php echo $admin?'data-ticket-admin data-can-manage="1"':'data-ticket-student'; ?>><p>Chargement…</p></div>
<script>window.OuinpoTicketing={root:'/api',nonce:'fixture',name:'PataDesk'};</script><script src="/assets/js/front/ticket-simulator.js"></script><?php if($admin):?><script src="/assets/js/admin/ticket-simulator-admin.js"></script><?php endif;?></body></html>
