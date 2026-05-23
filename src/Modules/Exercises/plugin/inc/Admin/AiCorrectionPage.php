<?php

namespace Ouinpo\Exercises\Admin;

use Ouinpo\Exercises\Services\CopyUploadService;
use Ouinpo\Exercises\Services\CorrectionBatchService;
use Ouinpo\Suite\Core\AiSettings;
use Ouinpo\Suite\Core\Capabilities;

defined('ABSPATH') || exit;

final class AiCorrectionPage
{
    public static function render(): void
    {
        if (!current_user_can(Capabilities::MANAGE_ASSESSMENTS) && !current_user_can('manage_options')) {
            wp_die('Accès refusé.');
        }
        CorrectionBatchService::ensure_schema();
        $assessments = CorrectionBatchService::assessments();
        $enabled = (int) AiSettings::get('ouinpo_ai_correction_scans_enabled') === 1;
        $config = [
            'rest' => [
                'batchesUrl' => rest_url('ouinpo/v1/ai-corrections/batches'),
                'nonce' => wp_create_nonce('wp_rest'),
                'enabled' => $enabled,
                'allowed' => array_keys(CopyUploadService::allowed_mimes()),
            ],
            'assessments' => $assessments,
        ];
        ?>
        <div class="wrap">
            <h1>Correction assistée par IA</h1>
            <div class="notice notice-warning">
                <p>Les scans de copies peuvent contenir des données personnelles. Vérifiez le fournisseur IA utilisé et validez toujours les corrections proposées.</p>
            </div>
            <?php if (!$enabled): ?>
                <div class="notice notice-error"><p>Cette fonctionnalité est désactivée. Active l’option <code>ouinpo_ai_correction_scans_enabled</code> dans les réglages IA.</p></div>
            <?php endif; ?>

            <div id="ouinpo-ai-correction-root" class="postbox ouinpo-admin-postbox">
                <h2>1. Lot de correction</h2>
                <p>
                    <label>Devoir
                        <select id="ouinpo-correction-assessment">
                            <option value="0">Choisir un devoir</option>
                            <?php foreach ($assessments as $assessment): ?>
                                <option value="<?php echo (int) $assessment['id']; ?>" data-group="<?php echo (int) ($assessment['group_id'] ?? 0); ?>">
                                    <?php echo esc_html('#' . (int) $assessment['id'] . ' - ' . (string) $assessment['title'] . (!empty($assessment['group_label']) ? ' - ' . $assessment['group_label'] : '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="button" class="button button-primary" id="ouinpo-create-correction-batch">Créer le lot</button>
                </p>

                <div id="ouinpo-correction-status" aria-live="polite"></div>

                <div id="ouinpo-correction-workspace" hidden>
                    <p><button type="button" class="button" id="ouinpo-delete-correction-batch">Supprimer le lot et ses scans</button></p>
                    <h2>2. Import des copies</h2>
                    <p class="description">Formats acceptés : PDF, JPG, PNG, WebP. Sans OCR/vision disponible, colle le texte extrait manuellement.</p>
                    <p>
                        <label>Référence anonyme <input type="text" id="ouinpo-copy-ref" placeholder="anonyme-001"></label>
                        <label>ID élève optionnel <input type="number" id="ouinpo-copy-user-id" min="0" step="1" value="0"></label>
                    </p>
                    <p>
                        <label>Scan <input type="file" id="ouinpo-copy-file" accept=".pdf,.jpg,.jpeg,.png,.webp"></label>
                    </p>
                    <p>
                        <label>Texte OCR / transcription manuelle<br>
                            <textarea id="ouinpo-copy-manual-text" rows="6" class="large-text"></textarea>
                        </label>
                    </p>
                    <p><button type="button" class="button" id="ouinpo-upload-copy">Importer la copie</button></p>

                    <h2>3. Copies importées</h2>
                    <div id="ouinpo-copies-table"></div>
                    <div id="ouinpo-copy-detail"></div>
                </div>
            </div>
        </div>

        <script>
        window.ouinpoAiCorrection = <?php echo wp_json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        (function(){
            const cfg = window.ouinpoAiCorrection;
            const $ = (id) => document.getElementById(id);
            let batch = null;

            function esc(value){ return String(value || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])); }
            function notice(type, message){ $('ouinpo-correction-status').innerHTML = '<div class="notice notice-' + type + ' inline"><p>' + esc(message) + '</p></div>'; }
            async function postJson(url, body){
                const res = await fetch(url,{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json','X-WP-Nonce':cfg.rest.nonce},body:JSON.stringify(body)});
                const data = await res.json().catch(() => ({}));
                if(!res.ok) throw new Error(data.message || 'Erreur REST.');
                return data;
            }
            async function deleteJson(url){
                const res = await fetch(url,{method:'DELETE',credentials:'same-origin',headers:{'X-WP-Nonce':cfg.rest.nonce}});
                const data = await res.json().catch(() => ({}));
                if(!res.ok) throw new Error(data.message || 'Erreur REST.');
                return data;
            }
            async function postForm(url, form){
                const res = await fetch(url,{method:'POST',credentials:'same-origin',headers:{'X-WP-Nonce':cfg.rest.nonce},body:form});
                const data = await res.json().catch(() => ({}));
                if(!res.ok) throw new Error(data.message || 'Erreur REST.');
                return data;
            }
            function renderBatch(){
                $('ouinpo-correction-workspace').hidden = !batch;
                const copies = batch?.copies || [];
                if(!copies.length){ $('ouinpo-copies-table').innerHTML = '<p>Aucune copie importée.</p>'; return; }
                $('ouinpo-copies-table').innerHTML = '<table class="widefat striped"><thead><tr><th>Réf.</th><th>Fichier</th><th>État</th><th>Note proposée</th><th>Actions</th></tr></thead><tbody>' + copies.map(copy => {
                    let proposal = null;
                    try { proposal = copy.ai_proposal ? JSON.parse(copy.ai_proposal) : null; } catch(e){}
                    const note = proposal?.total ? proposal.total.suggested_points + ' / ' + proposal.total.max_points : '-';
                    return '<tr><td>' + esc(copy.student_ref) + '</td><td>' + esc(copy.file_name) + '</td><td>' + esc(copy.status) + (copy.error_message ? '<br><small>' + esc(copy.error_message) + '</small>' : '') + '</td><td>' + esc(note) + '</td><td><button class="button button-small" data-analyze="' + copy.id + '">Analyser avec l’IA</button> <button class="button button-small" data-detail="' + copy.id + '">Voir détail</button> <button class="button button-small" data-reject="' + copy.id + '">Rejeter</button></td></tr>';
                }).join('') + '</tbody></table>';
            }
            async function refresh(){
                if(!batch) return;
                const res = await fetch(cfg.rest.batchesUrl + '/' + batch.id, {headers:{'X-WP-Nonce':cfg.rest.nonce}, credentials:'same-origin'});
                const data = await res.json();
                batch = data.batch;
                renderBatch();
            }
            $('ouinpo-create-correction-batch').addEventListener('click', async () => {
                if(!cfg.rest.enabled){ notice('error', 'Correction IA de scans désactivée.'); return; }
                const select = $('ouinpo-correction-assessment');
                const assessmentId = parseInt(select.value, 10);
                if(!assessmentId){ notice('error', 'Choisis un devoir.'); return; }
                try {
                    const data = await postJson(cfg.rest.batchesUrl, {assessment_id: assessmentId, group_id: parseInt(select.selectedOptions[0].dataset.group || '0', 10)});
                    batch = data.batch;
                    notice('success', 'Lot créé.');
                    renderBatch();
                } catch(e){ notice('error', e.message); }
            });
            $('ouinpo-upload-copy').addEventListener('click', async () => {
                if(!batch){ notice('error', 'Crée d’abord un lot.'); return; }
                const file = $('ouinpo-copy-file').files[0];
                if(!file){ notice('error', 'Choisis un fichier.'); return; }
                const form = new FormData();
                form.append('copy_file', file);
                form.append('student_ref', $('ouinpo-copy-ref').value);
                form.append('student_user_id', $('ouinpo-copy-user-id').value);
                form.append('manual_text', $('ouinpo-copy-manual-text').value);
                try {
                    const data = await postForm(cfg.rest.batchesUrl + '/' + batch.id + '/copies', form);
                    batch = data.batch;
                    $('ouinpo-copy-file').value = '';
                    $('ouinpo-copy-manual-text').value = '';
                    $('ouinpo-copy-ref').value = '';
                    $('ouinpo-copy-user-id').value = '0';
                    notice('success', 'Copie importée.');
                    renderBatch();
                } catch(e){ notice('error', e.message); }
            });
            $('ouinpo-delete-correction-batch').addEventListener('click', async () => {
                if(!batch || !confirm('Supprimer ce lot et les scans associés ?')) return;
                try {
                    await deleteJson(cfg.rest.batchesUrl + '/' + batch.id);
                    batch = null;
                    $('ouinpo-correction-workspace').hidden = true;
                    $('ouinpo-copies-table').innerHTML = '';
                    $('ouinpo-copy-detail').innerHTML = '';
                    notice('success', 'Lot supprimé.');
                } catch(e){ notice('error', e.message); }
            });
            $('ouinpo-copies-table').addEventListener('click', async (event) => {
                const analyze = event.target.getAttribute('data-analyze');
                const detail = event.target.getAttribute('data-detail');
                const reject = event.target.getAttribute('data-reject');
                try {
                    if(analyze){ notice('info','Analyse en cours…'); await postJson(cfg.rest.batchesUrl.replace('/batches','/copies/') + analyze + '/analyze', {}); await refresh(); notice('success','Proposition IA prête.'); }
                    if(reject){ await postJson(cfg.rest.batchesUrl.replace('/batches','/copies/') + reject + '/reject', {}); await refresh(); notice('success','Proposition rejetée.'); }
                    if(detail){ renderDetail(parseInt(detail,10)); }
                } catch(e){ notice('error', e.message); }
            });
            function renderDetail(id){
                const copy = (batch?.copies || []).find(c => parseInt(c.id,10) === id);
                if(!copy){ return; }
                let proposal = null;
                try { proposal = copy.ai_proposal ? JSON.parse(copy.ai_proposal) : null; } catch(e){}
                if(!proposal){ $('ouinpo-copy-detail').innerHTML = '<p>Aucune proposition disponible.</p>'; return; }
                $('ouinpo-copy-detail').innerHTML = '<h2>Détail - ' + esc(copy.student_ref) + '</h2>'
                    + '<p>Qualité : ' + esc(proposal.copy_quality?.confidence) + ' · Note : <input id="ouinpo-final-total" type="number" step="0.25" value="' + esc(proposal.total?.suggested_points) + '"> / ' + esc(proposal.total?.max_points) + '</p>'
                    + '<h3>Extrait OCR / transcription</h3><pre style="white-space:pre-wrap;max-height:220px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:8px">' + esc(copy.ocr_text || '') + '</pre>'
                    + (proposal.items || []).map((item, i) => '<div class="postbox" style="padding:12px"><h3>' + esc(item.exercise_title || ('Exercice #' + item.exercise_id)) + '</h3><p>Points <input data-item-points="' + i + '" type="number" step="0.25" value="' + esc(item.suggested_points) + '"> / ' + esc(item.max_points) + '</p><p><textarea data-item-feedback="' + i + '" rows="4" class="large-text">' + esc(item.feedback) + '</textarea></p></div>').join('')
                    + '<p><label>Appréciation globale<br><textarea id="ouinpo-global-feedback" rows="4" class="large-text">' + esc(proposal.global_feedback) + '</textarea></label></p>'
                    + '<p><button class="button button-primary" id="ouinpo-validate-correction">Valider cette correction</button></p>';
                $('ouinpo-validate-correction').addEventListener('click', async () => {
                    proposal.total.suggested_points = parseFloat($('ouinpo-final-total').value || '0');
                    proposal.global_feedback = $('ouinpo-global-feedback').value;
                    document.querySelectorAll('[data-item-points]').forEach(input => proposal.items[parseInt(input.dataset.itemPoints,10)].suggested_points = parseFloat(input.value || '0'));
                    document.querySelectorAll('[data-item-feedback]').forEach(input => proposal.items[parseInt(input.dataset.itemFeedback,10)].feedback = input.value);
                    try { await postJson(cfg.rest.batchesUrl.replace('/batches','/copies/') + id + '/validate', {correction: proposal}); await refresh(); notice('success','Correction validée.'); }
                    catch(e){ notice('error', e.message); }
                });
            }
        })();
        </script>
        <?php
    }
}
