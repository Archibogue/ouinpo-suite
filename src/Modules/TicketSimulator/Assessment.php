<?php
namespace Ouinpo\Suite\Modules\TicketSimulator;
defined('ABSPATH') || exit;

/** Assessment data is private by default. Never return the stored document to a student. */
final class Assessment
{
    public static function data(array $attempt): array { return json_decode($attempt['assessment'] ?? '', true) ?: []; }
    public static function settings(array $assignment): array { return json_decode($assignment['settings'] ?? '', true) ?: ['mode'=>'practice']; }
    public static function graded(array $attempt): bool { return (self::data($attempt)['settings']['mode'] ?? '') === 'graded'; }
    public static function open(array $settings, ?int $now = null): bool
    {
        $now ??= time();
        return (empty($settings['opens_at']) || $now >= strtotime($settings['opens_at']))
            && (empty($settings['due_at']) || $now <= strtotime($settings['due_at']) || ($settings['late_policy'] ?? 'block') === 'flag');
    }
    public static function writable(array $a): bool
    {
        $d = self::data($a);
        return !$d || (($d['state'] ?? 'working') === 'working' && self::open($d['settings']));
    }
    public static function aid(array $a, string $key): bool
    {
        $d = self::data($a);
        return !$d || (!empty($d['settings'][$key]) && self::writable($a));
    }
    private static function text($v, int $max = 10000): string
    {
        if (!is_string($v) || strlen($v) > $max) { throw new \InvalidArgumentException('Texte invalide ou trop long.'); }
        return sanitize_textarea_field($v);
    }
    public static function validate(array $s, array $scenario): array
    {
        $mode = $s['mode'] ?? 'practice';
        if (!in_array($mode, ['practice','graded'], true)) { throw new \InvalidArgumentException('Mode invalide.'); }
        $out = ['mode'=>$mode, 'label'=>self::text($s['label'] ?? '', 200)];
        foreach (['opens_at','due_at','publish_after'] as $key) {
            $v = $s[$key] ?? '';
            if (!is_string($v) || ($v !== '' && (!preg_match('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/D', $v) || strtotime($v) === false || gmdate('Y-m-d\TH:i:s\Z', strtotime($v)) !== $v))) { throw new \InvalidArgumentException('Date UTC invalide.'); }
            $out[$key] = $v;
        }
        if ($out['opens_at'] && $out['due_at'] && $out['opens_at'] >= $out['due_at']) { throw new \InvalidArgumentException('L’échéance doit suivre l’ouverture.'); }
        $out['attempts'] = $s['attempts'] ?? 1;
        if (!is_int($out['attempts']) || $out['attempts'] < 1 || $out['attempts'] > 100) { throw new \InvalidArgumentException('De 1 à 100 tentatives autorisées.'); }
        $out['late_policy'] = $s['late_policy'] ?? 'block';
        if (!in_array($out['late_policy'], ['block','flag'], true)) { throw new \InvalidArgumentException('Politique de retard invalide.'); }
        foreach (['hints','ai_advice','ai_dialogue'] as $key) {
            $out[$key] = $s[$key] ?? ($mode === 'practice');
            if (!is_bool($out[$key])) { throw new \InvalidArgumentException('Autorisation d’aide invalide.'); }
        }
        $out['rubric'] = $mode === 'graded' ? self::rubric($s['rubric'] ?? [], $scenario) : [];
        return $out;
    }
    public static function rubric(array $rows, array $scenario): array
    {
        if (!array_is_list($rows) || !$rows || count($rows) > 100) { throw new \InvalidArgumentException('La grille doit comporter de 1 à 100 critères.'); }
        $out = []; $ids = []; $total = 0;
        foreach ($rows as $i => $r) {
            if (!is_array($r)) { throw new \InvalidArgumentException('Critère invalide.'); }
            $id = $r['id'] ?? 'c' . ($i + 1);
            if (!is_string($id) || !preg_match('/^[a-zA-Z0-9_-]{1,80}$/D', $id) || isset($ids[$id])) { throw new \InvalidArgumentException('Identifiant de critère invalide.'); }
            $ids[$id] = true;
            $max = $r['max'] ?? null;
            if ((!is_int($max) && !is_float($max)) || !is_finite((float)$max) || $max < 0 || $max > 1000) { throw new \InvalidArgumentException('Maximum hors limites.'); }
            $row = ['id'=>$id, 'max'=>$max];
            foreach (['title','instruction','competence','private','ticket'] as $key) { $row[$key] = self::text($r[$key] ?? ''); }
            if (!trim($row['title'])) { throw new \InvalidArgumentException('Intitulé requis.'); }
            if ($row['ticket'] !== '' && !isset(TicketScenario::index($scenario['tickets'])[$row['ticket']])) { throw new \InvalidArgumentException('Portée du critère inconnue.'); }
            if ($row['ticket'] !== '' && !empty(TicketScenario::index($scenario['tickets'])[$row['ticket']]['optional'])) { throw new \InvalidArgumentException('Évaluer les extensions dans une activité séparée.'); }
            $total += $max; $out[] = $row;
        }
        if ($total <= 0) { throw new \InvalidArgumentException('Le total du barème doit être positif.'); }
        return $out;
    }
    public static function templates(): array
    {
        $groups = [
            'Qualification et réponse initiale'=>['Contexte et informations utiles','Nature et catégorie technique','Priorité argumentée','Orientation documentée','Réponse initiale et engagements'],
            'Diagnostic et traitement'=>['Symptôme et reproduction','Hypothèses et tests','Correction expliquée','Vérification et preuves','Communication et limites'],
            'Intervention complète'=>['Collecte et qualification','Priorité et orientation','Diagnostic et traitement','Vérification des résultats','Restitution et documentation'],
        ];
        $out = [];
        foreach ($groups as $name=>$titles) {
            $out[$name] = array_map(static fn($title,$i)=>['id'=>'c'.($i+1),'title'=>$title,'instruction'=>'Documenter et justifier : '.$title.'.','max'=>4,'competence'=>'B1.2','private'=>'Accepter toute démarche pertinente et étayée. La présence d’un texte ne prouve pas sa qualité.','ticket'=>''], $titles, array_keys($titles));
        }
        return $out;
    }
    public static function correction(array $rubric, array $input): array
    {
        $rows = []; $sum = 0; $complete = true;
        foreach ($rubric as $r) {
            $v = $input['criteria'][$r['id']] ?? [];
            $p = $v['points'] ?? null;
            if ($p !== null && ((!is_int($p) && !is_float($p)) || !is_finite((float)$p) || $p < 0 || $p > $r['max'])) { throw new \InvalidArgumentException('Points hors limites : '.$r['title']); }
            $complete = $complete && $p !== null; $sum += $p ?? 0;
            $rows[$r['id']] = ['points'=>$p,'comment'=>self::text($v['comment'] ?? '')];
        }
        $total = array_sum(array_column($rubric, 'max'));
        if ($total <= 0) { throw new \InvalidArgumentException('Barème nul.'); }
        return ['criteria'=>$rows,'general'=>self::text($input['general'] ?? ''),'verified_evidence'=>self::text($input['verified_evidence'] ?? ''), 'points'=>$complete ? $sum : null, 'total'=>$total, 'grade'=>$complete ? round(20*$sum/$total, 2, PHP_ROUND_HALF_UP) : null];
    }
    public static function publicData(array $a): array
    {
        $d = self::data($a);
        if (!$d) { return ['mode'=>'practice']; }
        $s = $d['settings'];
        $out = array_intersect_key($s, array_flip(['mode','label','opens_at','due_at','attempts','late_policy','publish_after','hints','ai_advice','ai_dialogue']));
        $out['state'] = $d['state'];
        $out['rubric'] = array_map(static fn($r)=>array_diff_key($r, ['private'=>true]), $s['rubric'] ?? []);
        $out['submissions'] = array_map(static fn($r)=>array_intersect_key($r,array_flip(['submitted_at','late','missing'])), $d['submissions'] ?? []);
        if ($d['state'] === 'published') { $out['result'] = $d['published']; }
        return $out;
    }
    public static function teacher(array $a): bool
    {
        return PermissionService::observe($a) && PermissionService::scenario((new ScenarioRepository())->get((int)$a['scenario_id']));
    }
    public static function missing(array $scenario, array $states): array
    {
        $out = [];
        foreach ($scenario['tickets'] as $t) {
            if (!empty($t['optional'])) { continue; }
            foreach (Pedagogy::missing($t, $states[$t['id']]) as $key) { $out[] = $t['id'].' : '.$key; }
            foreach (Pedagogy::traceMissing($scenario, $t, $states[$t['id']]) as $key) { $out[] = $t['id'].' : '.$key; }
        }
        if (!Pedagogy::finished($scenario, $states)) { $out[] = 'Parcours essentiel non terminé (remise autorisée).'; }
        return $out;
    }
    public static function read(int $id): array
    {
        return ScenarioRepository::transaction(static function () use ($id) {
            $a = (new AttemptRepository())->get($id, true);
            PermissionService::require(PermissionService::view($a));
            if (self::teacher($a)) {
                $data=self::data($a);
                foreach($data['submissions'] ?? [] as $i=>$copy) { $data['submissions'][$i]['markdown_html']=Presentation::markdown($copy['markdown'] ?? ''); }
                return $data;
            }
            return self::publicData($a);
        });
    }
    public static function change(int $id, string $op, array $input): array
    {
        return ScenarioRepository::transaction(static function () use ($id,$op,$input) {
            global $wpdb;
            $repo = new AttemptRepository(); $a = $repo->get($id, true); $d = self::data($a);
            if (!self::graded($a)) { throw new \DomainException('Cette tentative n’est pas notée.'); }
            if (($input['revision'] ?? null) !== (int)$a['revision']) { throw new \RuntimeException('La copie a changé. Actualisez.',409); }
            if ($op === 'submit') {
                PermissionService::require(PermissionService::edit($a));
                if (($input['confirm'] ?? false) !== true) { throw new \InvalidArgumentException('Confirmation de remise requise.'); }
                $scenario = json_decode($a['snapshot'],true); $states = $repo->states($id); $events = []; $after = 0;
                do { $page = (new EventRepository())->listing($id,$after); foreach ($page as $e) { $events[]=$e; $after=(int)$e['id']; } } while(count($page)===500);
                $d['submissions'][] = ['submitted_at'=>gmdate('c'),'late'=>!empty($d['settings']['due_at']) && time()>strtotime($d['settings']['due_at']), 'missing'=>self::missing($scenario,$states), 'scenario'=>$scenario,'states'=>$states,'events'=>$events,'rubric'=>$d['settings']['rubric'],'markdown'=>AttemptMarkdown::render($a,$states,$events)];
                $d['state']='submitted'; unset($d['draft'],$d['published']);
            } else {
                PermissionService::require(self::teacher($a));
                if (empty($d['submissions'])) { throw new \DomainException('Travail non remis : aucune note à attribuer.'); }
                if ($op === 'reopen') {
                    $reason = self::text($input['reason'] ?? '');
                    if (!trim($reason) || $d['state']==='working') { throw new \InvalidArgumentException('Motif de réouverture requis pour une copie remise.'); }
                    $d['state']='working';
                    // A reopening does not silently extend the deadline. Teacher specifies a new one.
                    $settings = $d['settings'];
                    if (isset($input['due_at'])) { $settings['due_at']=$input['due_at']; }
                    $d['settings']=self::validate($settings,json_decode($a['snapshot'],true));
                    unset($d['draft'],$d['published']);
                } elseif ($op === 'grade') {
                    if ($d['state']==='working') { throw new \DomainException('Attendez une nouvelle remise.'); }
                    $latest = array_key_last($d['submissions']);
                    $d['draft']=self::correction($d['submissions'][$latest]['rubric'],$input);
                    $d['state']='correcting'; unset($d['published']);
                } elseif ($op === 'publish') {
                    if ($d['state']!=='correcting' || !isset($d['draft']['grade'])) { throw new \DomainException('Corrigez tous les critères avant publication.'); }
                    if (!empty($d['settings']['publish_after']) && time()<strtotime($d['settings']['publish_after'])) { throw new \DomainException('La date minimale de publication n’est pas atteinte.'); }
                    $d['published']=$d['draft']; $d['state']='published';
                } else { throw new \InvalidArgumentException('Opération inconnue.'); }
            }
            $entry = ['operation'=>$op,'at'=>gmdate('c'),'actor'=>get_current_user_id(),'submission'=>count($d['submissions']),'reason'=>$reason ?? ''];
            if (in_array($op,['grade','publish'],true)) { $entry['correction']=$d['draft']; }
            $d['history'][]=$entry;
            ScenarioRepository::check($wpdb->update(ScenarioRepository::table('attempts'),['assessment'=>wp_json_encode($d),'revision'=>(int)$a['revision']+1],['id'=>$id]));
            return ['ok'=>true];
        });
    }
    public static function results(array $filter, bool $csv = false): array
    {
        global $wpdb;
        PermissionService::require(PermissionService::manage());
        $where = PermissionService::all() ? '1=1' : $wpdb->prepare('t.teacher_id=%d',get_current_user_id());
        if (!empty($filter['assignment'])) { $where.=$wpdb->prepare(' AND t.assignment_id=%d',(int)$filter['assignment']); }
        $rows=$wpdb->get_results('SELECT t.*,a.target_type,a.target_id FROM '.ScenarioRepository::table('attempts').' t JOIN '.ScenarioRepository::table('assignments')." a ON a.id=t.assignment_id WHERE $where ORDER BY t.id DESC",ARRAY_A) ?: [];
        $out=[];
        foreach($rows as $a) {
            if (!self::graded($a) || !self::teacher($a)) { continue; }
            if (!empty($filter['class'])) {
                $class=(int)$filter['class'];
                $target=in_array($a['target_type'],['class','subgroup'],true) && (int)explode(':',$a['target_id'])[0]===$class;
                if (!$target && !\Ouinpo\Suite\Core\Privacy\LearningAudiencePolicy::isRosteredClassStudent((int)$a['student_id'],$class)) { continue; }
            }
            if (!empty($filter['group'])) {
                $group=(string)$filter['group'];$class=(int)explode(':',$group)[0];
                $target=$a['target_type']==='subgroup' && $a['target_id']===$group;
                $member=\Ouinpo\Suite\Core\Privacy\LearningAudiencePolicy::isRosteredClassStudent((int)$a['student_id'],$class) && \Ouinpo\Suite\Core\ClassSubgroups::allows((int)$a['student_id'],[$class],[$group]);
                if (!$target && !$member) { continue; }
            }
            $d=self::data($a);
            if (!empty($filter['state']) && $filter['state']!==$d['state']) { continue; }
            $latest=empty($d['submissions']) ? [] : $d['submissions'][array_key_last($d['submissions'])];
            $metadata=Presentation::metadata($a);
            if (!empty($filter['search']) && stripos(remove_accents($metadata['student_name']),remove_accents(trim((string)$filter['search'])))===false) { continue; }
            $out[]=['id'=>(int)$a['id'],'assignment'=>(int)$a['assignment_id'],'student'=>(int)$a['student_id'],'state'=>$d['state'],'grade'=>$d['state']==='published' ? $d['published']['grade'] : null,'label'=>$d['settings']['label'],
                'submitted_at'=>$latest['submitted_at'] ?? null,'late'=>!empty($latest['late'])] + $metadata + Presentation::audience($a);
        }
        if (!$csv) { return $out; }
        $f=fopen('php://temp','r+'); fputcsv($f,['Tentative','Affectation','Étudiant','État','Note /20','Activité'],';', '"', '');
        foreach($out as $row) {
            $row['state']=Presentation::STATES[$row['state']] ?? $row['state'];
            $cells=array_values(array_intersect_key($row,array_flip(['id','assignment','student','state','grade','label'])));
            fputcsv($f,array_map([self::class,'csvCell'],$cells),';', '"', '');
        }
        rewind($f); $content=stream_get_contents($f); fclose($f);
        return ['filename'=>'patadesk-resultats.csv','csv'=>"\xEF\xBB\xBF".$content];
    }
    public static function csvCell($v): string
    {
        $v=(string)$v;
        return preg_match('/^[\s\x00-\x1f]*[=+@-]/u',$v) || preg_match('/^[\t\r\n]/',$v) ? "'".$v : $v;
    }
}
