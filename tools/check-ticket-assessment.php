<?php
declare(strict_types=1);
require __DIR__.'/check-ticket-simulator.php';
use Ouinpo\Suite\Modules\TicketSimulator\{Assessment,Pedagogy,ScenarioValidator,ScenarioAttempt,AttemptRepository,AttemptMarkdown,AttemptAdvice,TicketDialogue,StudentView,PermissionService,SimulationEngine};

$current=11;
$s=require dirname(__DIR__).'/src/Modules/TicketSimulator/demo.php';
$rubric=array_values(Assessment::templates())[0];
$settings=Assessment::validate(['mode'=>'graded','rubric'=>$rubric],$s);
check(!$settings['ai_dialogue'] && !$settings['ai_advice'] && !$settings['hints'],'Graded defaults disable aids and AI');
foreach ([0,-1,101,1.5] as $n) { rejects(fn()=>Assessment::validate(['attempts'=>$n],$s),'Invalid attempt limit rejected'); }
rejects(fn()=>Assessment::validate(['opens_at'=>'2026-02-30T10:00:00Z'],$s),'Invalid calendar date rejected');
rejects(fn()=>Assessment::validate(['opens_at'=>'2026-09-21T10:00:00Z','due_at'=>'2026-09-20T10:00:00Z'],$s),'Reversed opening window rejected');
check(!Assessment::open(['opens_at'=>'2099-01-01T00:00:00Z']),'Opening enforced');
check(!Assessment::open(['due_at'=>'2000-01-01T00:00:00Z','late_policy'=>'block']),'Deadline enforced');
check(Assessment::open(['due_at'=>'2000-01-01T00:00:00Z','late_policy'=>'flag']),'Late policy permits flagged submission');
$zero=$rubric;foreach($zero as &$r){$r['max']=0;}unset($r);
rejects(fn()=>Assessment::rubric($zero,$s),'Zero rubric refused');
$marks=Assessment::correction($rubric,[]);
check($marks['grade']===null && $marks['criteria']['c1']['points']===null,'Unmarked criteria are not zeros and no final grade');
rejects(fn()=>Assessment::correction($rubric,['criteria'=>['c1'=>['points'=>5]]]),'Over maximum refused');
rejects(fn()=>Assessment::correction($rubric,['criteria'=>['c1'=>['points'=>-1]]]),'Negative points refused');
$input=['criteria'=>[],'general'=>'PUBLIC_FEEDBACK','verified_evidence'=>'Vérification locale déclarée par le professeur'];
foreach($rubric as $r){$input['criteria'][$r['id']]=['points'=>3,'comment'=>'PUBLIC_CRITERION'];}
check(Assessment::correction($rubric,$input)['grade']===15.0,'Manual points converted to /20');
$round=$rubric;$round[0]['max']=3;
check(Assessment::correction($round,$input)['grade']===15.79,'Grade rounded to two decimals');

$short=$s;$short['completion_status']='qualified';$short['tickets']=[$s['tickets'][0]];
$t=$short['tickets'][0];$state=ScenarioAttempt::initial($t);
$state['fields']=array_merge($state['fields'],['nature'=>'incident','impact'=>'Moyen','urgency'=>'Moyenne','priority'=>'Normale','priority_justification'=>'Une priorité différente et défendable']);
$state['evidence']=['context'=>'Contexte','information'=>'Informations','initial_response'=>'Réponse initiale'];$state['exercise_completed']=true;
check(Pedagogy::finished($short,[$t['id']=>$state]) && $state['status']==='new','Qualification ends exercise without resolving ticket');
$short['completion_status']='oriented';check(!Pedagogy::finished($short,[$t['id']=>$state]),'Orientation requires documented trace');
$state['evidence']['orientation']='N2 : périmètre, destinataire et motif';check(Pedagogy::finished($short,[$t['id']=>$state]),'Documented orientation ends essential exercise');
$extra=$t;$extra['id']='extra';$extra['optional']=true;$short['tickets'][]=$extra;
check(Pedagogy::finished($short,[$t['id']=>$state]),'Optional extension does not block completion');
$note=$t;$note['actions'][]=['id'=>'internal','label'=>'Ajouter une note technique','type'=>'technical_note','requires_message'=>true];
[$ignored,$events]=(new SimulationEngine())->perform($note,ScenarioAttempt::initial($note),'internal',['message'=>'INTERNAL']);
check(in_array('technical_note',array_column($events,'type'),true) && !in_array('user_message',array_column($events,'type'),true),'Internal note never becomes requester message');

$wpdb->attempt=['id'=>1,'scenario_id'=>1,'assignment_id'=>7,'student_id'=>11,'teacher_id'=>20,'status'=>'active','revision'=>0,'snapshot'=>json_encode($s),'started_at'=>'2026-09-20 10:00:00','ended_at'=>null,'assessment'=>json_encode(['settings'=>$settings,'state'=>'working','submissions'=>[],'history'=>[]])];
$wpdb->scenario=['id'=>1,'owner_id'=>20,'status'=>'published','definition'=>json_encode($s)];
$wpdb->states=[];foreach($s['tickets'] as $ticket){$wpdb->states[]=['ticket_key'=>$ticket['id'],'state'=>json_encode(ScenarioAttempt::initial($ticket))];}
$repo=new AttemptRepository();
rejects(fn()=>TicketDialogue::send(1,$s['tickets'][0]['id'],0,'requester','Question'),'Direct AI dialogue denied');
rejects(fn()=>$repo->mutate(1,$s['tickets'][0]['id'],0,'reset_ticket',['confirm_reset'=>true]),'Graded reset denied before submission');
$current=12;rejects(fn()=>Assessment::read(1),'Foreign student cannot read assessment');
rejects(fn()=>Assessment::change(1,'submit',['revision'=>0,'confirm'=>true]),'Foreign student cannot submit');
$current=20;rejects(fn()=>Assessment::change(1,'grade',['revision'=>0]+$input),'Unsubmitted work cannot be graded');
$current=11;Assessment::change(1,'submit',['revision'=>0,'confirm'=>true]);
$d=Assessment::data($wpdb->attempt);
check($d['state']==='submitted' && count($d['submissions'][0]['missing'])>0,'Incomplete work can be submitted');
$frozen=$d['submissions'][0];
check(!PermissionService::edit($wpdb->attempt),'Submitted work locked');
rejects(fn()=>$repo->mutate(1,$s['tickets'][0]['id'],1,'note',['message'=>'Change']),'Note blocked after submission');
rejects(fn()=>Assessment::change(1,'submit',['revision'=>1,'confirm'=>true]),'Duplicate submission blocked');
check(!str_contains(AttemptAdvice::download(1)['markdown'],'Conseils de SegFault'),'AI export bypass denied without contacting provider');
$current=21;rejects(fn()=>Assessment::change(1,'grade',['revision'=>1]+$input),'Unrelated observer cannot grade');
$current=20;Assessment::change(1,'grade',['revision'=>1]+$input);
$current=11;$public=Assessment::read(1);$export=AttemptMarkdown::download(1)['markdown'];
check(!isset($public['result']) && !str_contains(json_encode($public),'PUBLIC_FEEDBACK') && !str_contains($export,'PUBLIC_FEEDBACK'),'Draft correction hidden in API and Markdown');
check(!str_contains(json_encode($public),'private') && !isset($public['history']),'Private rubric and correction history hidden');
$current=20;Assessment::change(1,'publish',['revision'=>2]);
$current=11;check(Assessment::read(1)['result']['grade']===15,'Published grade visible');
check(str_contains(AttemptMarkdown::download(1)['markdown'],'PUBLIC_FEEDBACK'),'Published feedback exportable');
$current=20;$changed=$input;$changed['criteria']['c1']['points']=2;
Assessment::change(1,'grade',['revision'=>3]+$changed);
$current=11;check(!isset(Assessment::read(1)['result']),'Changing published grade requires republication');
$current=20;rejects(fn()=>Assessment::change(1,'reopen',['revision'=>4,'reason'=>'']),'Reopening needs reason');
Assessment::change(1,'reopen',['revision'=>4,'reason'=>'Compléter les preuves']);
$current=11;check(PermissionService::edit($wpdb->attempt),'Reopening restores permitted editing');
$wpdb->scenario['definition']=json_encode(['title'=>'CHANGED MODEL']);
Assessment::change(1,'submit',['revision'=>5,'confirm'=>true]);
$d=Assessment::data($wpdb->attempt);
check(count($d['submissions'])===2 && $d['submissions'][0]===$frozen,'Resubmission preserves first work, scenario, rubric and events');
check(count(array_filter($d['history'],fn($h)=>$h['operation']==='reopen' && $h['reason']==='Compléter les preuves'))===1,'Reopening reason retained');
check(count(array_filter($d['history'],fn($h)=>isset($h['correction'])))===3,'Published and draft correction revisions retained');
foreach(['=SUM(A1:A2)',' +CMD','@x',"\t=1",'-1'] as $v){check(str_starts_with(Assessment::csvCell($v),"'"),'CSV formula injection neutralized');}
foreach(glob(__DIR__.'/templates/patadesk/*-v2.json') as $file){$scenario=json_decode(file_get_contents($file),true);check(ScenarioValidator::validate($scenario)===$scenario,'New SPOPI scenario validates: '.basename($file));}
// Exercise the actual start path with multiple assignments sharing a scenario.
final class AssessmentStartDb {
    public string $prefix='custom_'; public int $insert_id=0;
    public array $attempts=[];public array $assignments=[];public array $scenario;
    public function prepare($sql,...$params){foreach($params as $p){$sql=preg_replace('/%[ds]/',is_numeric($p)?(string)$p:"'".addslashes((string)$p)."'",$sql,1);}return $sql;}
    public function get_row($sql,$format){preg_match('/WHERE id=(\d+)/',$sql,$m);$id=(int)($m[1]??0);return str_contains($sql,'ticket_scenarios')?$this->scenario:(str_contains($sql,'ticket_assignments')?($this->assignments[$id]??null):($this->attempts[$id]??null));}
    public function get_var($sql){
        preg_match('/assignment_id=(\d+)/',$sql,$a);preg_match('/student_id=(\d+)/',$sql,$u);
        if(!$a || !$u){throw new RuntimeException('Unexpected lookup or attempt isolation missing: '.$sql);}
        $rows=array_filter($this->attempts,fn($r)=>(int)$r['assignment_id']===(int)$a[1] && (int)$r['student_id']===(int)$u[1]);
        if(str_contains($sql,'COUNT(*)'))return count($rows);
        $rows=array_filter($rows,fn($r)=>$r['status']!=='archived');return $rows?max(array_keys($rows)):null;
    }
    public function query($sql){return 1;}
    public function insert($table,$data){if(str_ends_with($table,'ticket_attempts')){$this->insert_id++;$this->attempts[$this->insert_id]=$data+['id'=>$this->insert_id,'status'=>'active','revision'=>0,'ended_at'=>null];}return 1;}
}
$wpdb=new AssessmentStartDb();$current=11;
$wpdb->scenario=['id'=>1,'owner_id'=>20,'status'=>'published','definition'=>json_encode($s)];
foreach([7=>'practice',8=>'graded',9=>'graded'] as $id=>$mode){$config=Assessment::validate(['mode'=>$mode,'rubric'=>$rubric,'attempts'=>2],$s);$wpdb->assignments[$id]=['id'=>$id,'scenario_id'=>1,'target_type'=>'user','target_id'=>'11','active'=>1,'settings'=>json_encode($config)];}
$training=$repo->start(7);$graded=$repo->start(8);
check($training!==$graded,'Same scenario training and grading have independent attempts');
check($repo->start(8)===$graded,'Repeated start returns only matching assignment attempt');
rejects(fn()=>$repo->start(8,true),'Cannot restart unsubmitted graded work');
$d=Assessment::data($wpdb->attempts[$graded]);$d['state']='submitted';$wpdb->attempts[$graded]['assessment']=json_encode($d);
$second=$repo->start(8,true);check($second!==$graded,'Allowed second attempt starts independently');
$d=Assessment::data($wpdb->attempts[$second]);$d['state']='submitted';$wpdb->attempts[$second]['assessment']=json_encode($d);
rejects(fn()=>$repo->start(8,true),'Attempt cap includes previous submitted attempts');
$config=Assessment::settings($wpdb->assignments[9]);$config['opens_at']='2099-01-01T00:00:00Z';$wpdb->assignments[9]['settings']=json_encode($config);
rejects(fn()=>$repo->start(9),'Direct start before opening denied');
$config['opens_at']='';$config['due_at']='2000-01-01T00:00:00Z';$wpdb->assignments[9]['settings']=json_encode($config);
rejects(fn()=>$repo->start(9),'Direct start after blocked deadline denied');
$config['late_policy']='flag';$wpdb->assignments[9]['settings']=json_encode($config);
check($repo->start(9)>0,'Direct start allowed by explicit late policy');
$current=12;rejects(fn()=>$repo->start(7),'Foreign student cannot start individual assignment');
echo "Total: $checks checks passed including assessment. No WordPress/MySQL or browser claim.\n";
