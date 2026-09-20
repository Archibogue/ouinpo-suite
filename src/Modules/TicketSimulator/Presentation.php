<?php
namespace Ouinpo\Suite\Modules\TicketSimulator;
defined('ABSPATH') || exit;

/** Read-only display adapters; never rewrite a scenario, submission or grade. */
final class Presentation
{
    public const STATES = ['working'=>'À remettre','submitted'=>'Remis','correcting'=>'Correction en cours','published'=>'Résultat publié','active'=>'Parcours en cours','completed'=>'Parcours terminé','archived'=>'Archivée'];
    public const TRACES = ['context'=>'Contexte et reformulation','information'=>'Informations recherchées / manquantes','initial_response'=>'Réponse initiale et délai justifié','orientation'=>'Orientation documentée','symptom'=>'Symptôme et reproduction','hypothesis'=>'Hypothèse et test','observed'=>'Résultat observé','correction'=>'Correction réalisée','verification'=>'Vérification après correction','proof'=>'Preuves textuelles / références de fichiers','reflection'=>'Bilan personnel'];
    public static function student(int $id): string
    {
        $user = get_user_by('id', $id);
        return $user ? (\Ouinpo\Suite\Core\StudentName::format($user) ?: 'Élève #'.$id) : 'Compte indisponible (#'.$id.')';
    }
    public static function metadata(array $a): array
    {
        $snapshot = json_decode($a['snapshot'] ?? '', true) ?: [];
        $assessment = Assessment::data($a);
        return ['scenario_title'=>$snapshot['title'] ?? 'Scénario #'.$a['scenario_id'],
            'activity_name'=>$assessment['settings']['label'] ?? '', 'student_name'=>self::student((int)$a['student_id']),
            'configured_activity'=>!empty($assessment)];
    }
    public static function audience(array $a): array
    {
        global $wpdb;
        $classes=[]; $groups=[];
        $rows=$wpdb->get_results($wpdb->prepare("SELECT g.id,g.label FROM {$wpdb->prefix}ouin_exo_groups g JOIN {$wpdb->prefix}ouin_exo_group_members m ON m.group_id=g.id WHERE m.user_id=%d AND m.role='student'",(int)$a['student_id']),ARRAY_A) ?: [];
        if (in_array($a['target_type'] ?? '',['class','subgroup'],true)) {
            $cid=(int)explode(':',$a['target_id'])[0];
            $row=$wpdb->get_row($wpdb->prepare("SELECT id,label FROM {$wpdb->prefix}ouin_exo_groups WHERE id=%d",$cid),ARRAY_A);
            if($row)$rows[]=$row;
        }
        foreach($rows as $c) {
            $cid=(int)$c['id'];$classes[(string)$cid]=['id'=>(string)$cid,'label'=>$c['label']];
            foreach(\Ouinpo\Suite\Core\ClassSubgroups::all($cid) as $key=>$group) {
                $gid=$cid.':'.$key;
                if(in_array((int)$a['student_id'],array_map('intval',$group['members'] ?? []),true) || ($a['target_id'] ?? '')===$gid) {
                    $groups[$gid]=['id'=>$gid,'label'=>$c['label'].' / '.$group['label']];
                }
            }
        }
        return ['classes'=>array_values($classes),'groups'=>array_values($groups)];
    }
    public static function readableMarkdown(string $markdown): string
    {
        $markdown=preg_replace_callback('/^État : (working|submitted|correcting|published)\s*$/m',static fn($m)=>'Remise et correction au moment de la capture : '.self::STATES[$m[1]],$markdown) ?? $markdown;
        // Older copies contain JSON inside journal blockquotes. Decode only that known envelope.
        return preg_replace_callback('/^(>\s*)?Traces déclarées par l’élève \(à vérifier\) : (\{[^\r\n]*\})\s*$/mu', static function($m) {
            $data=json_decode($m[2],true);
            if(!is_array($data))return $m[0];
            $lines=['Traces déclarées par l’élève — à vérifier :'];
            foreach(self::TRACES as $key=>$label) {
                if(isset($data[$key]) && is_string($data[$key]) && trim($data[$key])!=='') {
                    $value=str_replace(["\r\n","\r"],"\n",$data[$key]);
                    // Escape Markdown metacharacters: a trace is text, not new document markup.
                    $value=preg_replace('/([\\\\`*_{}\[\]()#+.!<>|~-])/u','\\\\$1',$value);
                    $lines[]=$label.' : '.str_replace("\n","\n> ",$value);
                }
            }
            return implode("\n\n",array_map(static fn($line)=>'> '.$line,$lines));
        },$markdown) ?? $markdown;
    }
    public static function markdown(string $markdown): string
    {
        // Same bundled parser and safe-mode + WordPress sanitation as Submissions/MarkdownPreview.
        if(!class_exists('Parsedown'))require_once dirname(__DIR__).'/SegFault/plugin/libs/parsedown/Parsedown.php';
        $parser=new \Parsedown();$parser->setSafeMode(true);
        $html=wp_kses_post($parser->text(self::readableMarkdown($markdown)));
        // Mark known UTC timestamps for display in the same browser timezone as date inputs.
        $parts=preg_split('/(<[^>]*>)/',$html,-1,PREG_SPLIT_DELIM_CAPTURE);
        foreach($parts as $i=>$part) {
            if($i%2===0)$parts[$i]=preg_replace_callback('/\b(\d{4}-\d{2}-\d{2}) (\d{2}:\d{2}:\d{2}) UTC\b/',static fn($m)=>'<time datetime="'.$m[1].'T'.$m[2].'Z">'.$m[0].'</time>',$part) ?? $part;
        }
        $html=implode('',$parts);
        // Copies are documents, not remote content loaders. Do not fetch embedded images.
        return preg_replace('/<img\b[^>]*>/i','',$html) ?? '';
    }
}
