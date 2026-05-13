<?php
namespace Ouinpo\Exercises\Admin;

use Ouinpo\Suite\Core\Capabilities;

defined('ABSPATH') || exit;

class Screen_Import_Competencies {
    public static function render() {
        if (!Capabilities::can(Capabilities::MANAGE_COMPETENCIES)) return;
        global $wpdb; $p=$wpdb->prefix.'ouin_exo_'; $messages=[];

        if (!empty($_POST['ouin_comp_import_nonce']) && wp_verify_nonce($_POST['ouin_comp_import_nonce'],'import_comp') && !empty($_FILES['csv']['tmp_name'])) {
            $fh = fopen($_FILES['csv']['tmp_name'], 'r');
            if ($fh) {
                $header = fgetcsv($fh, 0, ';'); $map = array_flip($header ?: []);
                $required = ['code','label','track']; $missing = array_diff($required, array_keys($map));
                if ($missing) { $messages[] = '<div class="notice notice-error"><p>Colonnes manquantes: '.esc_html(implode(', ',$missing)).'</p></div>'; }
                else {
                    $count=0; $updated=0;
                    while (($row = fgetcsv($fh, 0, ';')) !== false) {
                        $code = sanitize_text_field($row[$map['code']] ?? ''); if (!$code) continue;
                        $data = [
                            'code'=>$code,
                            'label'=>wp_kses_post($row[$map['label']] ?? ''),
                            'track'=>in_array($row[$map['track']] ?? 'NSI',['SNT','NSI'],true)?($row[$map['track']] ?? 'NSI'):'NSI',
                            'cycle'=>sanitize_text_field($row[$map['cycle']] ?? ''),
                            'reference_url'=>esc_url_raw($row[$map['reference_url']] ?? ''),
                        ];
                        $has=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}competencies WHERE code=%s",$code));
                        if ($has) { $wpdb->update($p.'competencies',$data,['code'=>$code]); $updated++; }
                        else { $wpdb->insert($p.'competencies',$data); $count++; }
                    }
                    fclose($fh);
                    $messages[] = '<div class="notice notice-success"><p>Import terminé : '.intval($count).' ajout(s), '.intval($updated).' mise(s) à jour.</p></div>';
                }
            } else { $messages[] = '<div class="notice notice-error"><p>Impossible de lire le fichier.</p></div>'; }
        } ?>
        <div class="wrap"><h1>Import Compétences (CSV)</h1>
            <?php foreach($messages as $m) echo $m; ?>
            <p>CSV (séparateur <code>;</code>) avec en-têtes : <code>code;label;track;cycle;reference_url</code></p>
            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('import_comp','ouin_comp_import_nonce'); ?>
                <input type="file" name="csv" accept=".csv" required>
                <p><button class="button button-primary">Importer</button></p>
            </form>
        </div><?php
    }
}
