<?php

namespace Ouinpo\Exercises\Admin;

use Ouinpo\Exercises\Services\FileCorrectionBatchService;
use Ouinpo\Exercises\Services\StudentFileExtractService;
use Ouinpo\Exercises\Services\StudentFileUploadService;
use Ouinpo\Suite\Core\AiSettings;
use Ouinpo\Suite\Core\Capabilities;

defined('ABSPATH') || exit;

final class AiFileCorrectionPage
{
    public static function render(): void
    {
        if (!current_user_can(Capabilities::MANAGE_ASSESSMENTS) && !current_user_can('manage_options')) {
            wp_die('AccÃ¨s refusÃ©.');
        }

        FileCorrectionBatchService::ensure_schema();
        $sources = FileCorrectionBatchService::sources();
        $enabled = (int) AiSettings::get('ouinpo_ai_file_correction_enabled') === 1;
        $config = [
            'rest' => [
                'batchesUrl' => rest_url('ouinpo/v1/ai-file-corrections/batches'),
                'submissionsUrl' => rest_url('ouinpo/v1/ai-file-corrections/submissions'),
                'nonce' => wp_create_nonce('wp_rest'),
                'enabled' => $enabled,
                'allowed' => StudentFileExtractService::allowed_extensions(),
                'blocked' => StudentFileExtractService::blocked_extensions(),
            ],
            'sources' => $sources,
        ];
        ?>
        <div class="wrap">
            <h1>Correction assistÃ©e par IA - fichiers Ã©lÃ¨ves</h1>
            <p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=ouinpo-ai-corrections')); ?>">Aller au workflow scans/OCR</a></p>
            <div class="notice notice-warning">
                <p>Les fichiers rendus par les Ã©lÃ¨ves peuvent contenir des donnÃ©es personnelles. VÃ©rifiez le fournisseur IA utilisÃ© et validez toujours les corrections proposÃ©es.</p>
            </div>
            <?php if (!$enabled): ?>
                <div class="notice notice-error"><p>Cette fonctionnalitÃ© est dÃ©sactivÃ©e. Active lâ€™option <code>ouinpo_ai_file_correction_enabled</code> dans les rÃ©glages IA.</p></div>
            <?php endif; ?>

            <div id="ouinpo-ai-file-correction-root" class="postbox ouinpo-admin-postbox">
                <h2>1. Contexte de correction</h2>
                <p>
                    <label>Type
                        <select id="ouinpo-file-context-type">
                            <option value="assessment">Devoir existant</option>
                            <option value="exercise">Exercice existant</option>
                            <option value="practical">Sujet pratique</option>
                        </select>
                    </label>
                    <label>Contexte
                        <select id="ouinpo-file-context-id"></select>
                    </label>
                    <label>Classe/groupe
                        <select id="ouinpo-file-group-id">
                            <option value="0">Non prÃ©cisÃ©</option>
                            <?php foreach ((array) ($sources['groups'] ?? []) as $group): ?>
                                <option value="<?php echo (int) $group['id']; ?>"><?php echo esc_html((string) $group['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button type="button" class="button button-primary" id="ouinpo-create-file-batch">CrÃ©er le lot fichiers</button>
                </p>

                <div id="ouinpo-file-correction-status" aria-live="polite"></div>

                <div id="ouinpo-file-workspace" hidden>
                    <p><button type="button" class="button" id="ouinpo-delete-file-batch">Supprimer le lot et ses fichiers</button></p>
                    <h2>2. Import des rendus</h2>
                    <p class="description">Formats acceptÃ©s : <?php echo esc_html(implode(', ', StudentFileExtractService::allowed_extensions())); ?>. Formats refusÃ©s explicitement : <?php echo esc_html(implode(', ', StudentFileExtractService::blocked_extensions())); ?>. Le code est lu uniquement, jamais exÃ©cutÃ©.</p>
                    <p>
                        <label>RÃ©fÃ©rence anonyme <input type="text" id="ouinpo-file-ref" placeholder="anonyme-001"></label>
                        <label>ID Ã©lÃ¨ve optionnel <input type="number" id="ouinpo-file-user-id" min="0" step="1" value="0"></label>
                    </p>
                    <p>
                        <label>Fichiers ou ZIP <input type="file" id="ouinpo-student-files" multiple accept=".py,.txt,.md,.sql,.html,.css,.js,.json,.csv,.xml,.yml,.yaml,.zip"></label>
                    </p>
                    <p><button type="button" class="button" id="ouinpo-upload-student-files">Importer les rendus</button></p>

                    <h2>3. Rendus importÃ©s</h2>
                    <div id="ouinpo-file-submissions-table"></div>
                    <div id="ouinpo-file-submission-detail"></div>
                </div>
            </div>
        </div>

        <script>
        window.ouinpoAiFileCorrection = <?php echo wp_json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        (function(){
            const cfg = window.ouinpoAiFileCorrection;
            const $ = (id) => document.getElementById(id);
            let batch = null;
            const sourceMap = {
                assessment: cfg.sources.assessments || [],
                exercise: cfg.sources.exercises || [],
                practical: cfg.sources.practical_subjects || []
            };

            function esc(value){ return String(value || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c])); }
            function notice(type, message){ $('ouinpo-file-correction-status').innerHTML = '<div class="notice notice-' + type + ' inline"><p>' + esc(message) + '</p></div>'; }
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
            function renderContextOptions(){
                const type = $('ouinpo-file-context-type').value;
                const rows = sourceMap[type] || [];
                $('ouinpo-file-context-id').innerHTML = '<option value="0">Choisir</option>' + rows.map(row => {
                    const suffix = row.group_label ? ' - ' + row.group_label : (row.difficulty_label ? ' - ' + row.difficulty_label : '');
                    return '<option value="' + esc(row.id) + '" data-group="' + esc(row.group_id || 0) + '">#' + esc(row.id) + ' - ' + esc(row.title) + esc(suffix) + '</option>';
                }).join('');
            }
            function proposalOf(copy){
                try { return copy.ai_proposal ? JSON.parse(copy.ai_proposal) : null; } catch(e){ return null; }
            }
            function jsonArray(value){
                try { const parsed = value ? JSON.parse(value) : []; return Array.isArray(parsed) ? parsed : []; } catch(e){ return []; }
            }
            function renderBatch(){
                $('ouinpo-file-workspace').hidden = !batch;
                const copies = batch?.copies || [];
                if(!copies.length){ $('ouinpo-file-submissions-table').innerHTML = '<p>Aucun rendu importÃ©.</p>'; return; }
                $('ouinpo-file-submissions-table').innerHTML = '<table class="widefat striped"><thead><tr><th>RÃ©f.</th><th>Fichiers</th><th>Ã‰tat</th><th>Note proposÃ©e</th><th>Actions</th></tr></thead><tbody>' + copies.map(copy => {
                    const proposal = proposalOf(copy);
                    const note = proposal?.total ? proposal.total.suggested_points + ' / ' + proposal.total.max_points : '-';
                    const warnings = copy.extraction_warnings ? '<br><small>' + esc(jsonArray(copy.extraction_warnings).join(' ')) + '</small>' : '';
                    return '<tr><td>' + esc(copy.student_ref) + '</td><td>' + esc(copy.file_name) + warnings + '</td><td>' + esc(copy.status) + (copy.error_message ? '<br><small>' + esc(copy.error_message) + '</small>' : '') + '</td><td>' + esc(note) + '</td><td><button class="button button-small" data-analyze="' + copy.id + '">Analyser avec lâ€™IA</button> <button class="button button-small" data-detail="' + copy.id + '">Voir dÃ©tail</button> <button class="button button-small" data-reject="' + copy.id + '">Rejeter</button></td></tr>';
                }).join('') + '</tbody></table>';
            }
            async function refresh(){
                if(!batch) return;
                const res = await fetch(cfg.rest.batchesUrl + '/' + batch.id, {headers:{'X-WP-Nonce':cfg.rest.nonce}, credentials:'same-origin'});
                const data = await res.json();
                batch = data.batch;
                renderBatch();
            }
            $('ouinpo-file-context-type').addEventListener('change', renderContextOptions);
            renderContextOptions();
            $('ouinpo-create-file-batch').addEventListener('click', async () => {
                if(!cfg.rest.enabled){ notice('error', 'Correction IA de fichiers dÃ©sactivÃ©e.'); return; }
                const select = $('ouinpo-file-context-id');
                const contextId = parseInt(select.value, 10);
                if(!contextId){ notice('error', 'Choisis un contexte.'); return; }
                try {
                    const data = await postJson(cfg.rest.batchesUrl, {context_type: $('ouinpo-file-context-type').value, context_id: contextId, group_id: parseInt($('ouinpo-file-group-id').value || select.selectedOptions[0].dataset.group || '0', 10)});
                    batch = data.batch;
                    notice('success', 'Lot fichiers crÃ©Ã©.');
                    renderBatch();
                } catch(e){ notice('error', e.message); }
            });
            $('ouinpo-upload-student-files').addEventListener('click', async () => {
                if(!batch){ notice('error', 'CrÃ©e dâ€™abord un lot.'); return; }
                const files = Array.from($('ouinpo-student-files').files || []);
                if(!files.length){ notice('error', 'Choisis au moins un fichier.'); return; }
                const form = new FormData();
                files.forEach(file => form.append('student_files[]', file));
                form.append('student_ref', $('ouinpo-file-ref').value);
                form.append('student_user_id', $('ouinpo-file-user-id').value);
                try {
                    const data = await postForm(cfg.rest.batchesUrl + '/' + batch.id + '/submissions', form);
                    batch = data.batch;
                    $('ouinpo-student-files').value = '';
                    $('ouinpo-file-ref').value = '';
                    $('ouinpo-file-user-id').value = '0';
                    notice('success', 'Rendu importÃ©.');
                    renderBatch();
                } catch(e){ notice('error', e.message); }
            });
            $('ouinpo-delete-file-batch').addEventListener('click', async () => {
                if(!batch || !confirm('Supprimer ce lot et les fichiers associÃ©s ?')) return;
                try {
                    await deleteJson(cfg.rest.batchesUrl + '/' + batch.id);
                    batch = null;
                    $('ouinpo-file-workspace').hidden = true;
                    $('ouinpo-file-submissions-table').innerHTML = '';
                    $('ouinpo-file-submission-detail').innerHTML = '';
                    notice('success', 'Lot supprimÃ©.');
                } catch(e){ notice('error', e.message); }
            });
            $('ouinpo-file-submissions-table').addEventListener('click', async (event) => {
                const analyze = event.target.getAttribute('data-analyze');
                const detail = event.target.getAttribute('data-detail');
                const reject = event.target.getAttribute('data-reject');
                try {
                    if(analyze){ notice('info','Analyse statique en coursâ€¦'); await postJson(cfg.rest.submissionsUrl + '/' + analyze + '/analyze', {}); await refresh(); notice('success','Proposition IA prÃªte.'); }
                    if(reject){ await postJson(cfg.rest.submissionsUrl + '/' + reject + '/reject', {}); await refresh(); notice('success','Proposition rejetÃ©e.'); }
                    if(detail){ renderDetail(parseInt(detail,10)); }
                } catch(e){ notice('error', e.message); }
            });
            function renderDetail(id){
                const copy = (batch?.copies || []).find(c => parseInt(c.id,10) === id);
                if(!copy){ return; }
                const proposal = proposalOf(copy);
                const manifest = jsonArray(copy.file_manifest);
                let html = '<h2>DÃ©tail - ' + esc(copy.student_ref) + '</h2>'
                    + '<h3>Fichiers transmis</h3><ul>' + manifest.map(file => '<li>' + esc(file.filename) + ' (' + esc(file.language) + ')' + (file.truncated ? ' - tronquÃ©' : '') + '</li>').join('') + '</ul>'
                    + '<h3>Contenu extrait</h3><pre style="white-space:pre-wrap;max-height:260px;overflow:auto;background:#fff;border:1px solid #ccd0d4;padding:8px">' + esc(copy.extracted_content || copy.ocr_text || '') + '</pre>';
                if(!proposal){
                    $('ouinpo-file-submission-detail').innerHTML = html + '<p>Aucune proposition disponible.</p>';
                    return;
                }
                html += '<p>QualitÃ© : ' + esc(proposal.submission_quality?.confidence) + ' Â· Note : <input id="ouinpo-file-final-total" type="number" step="0.25" value="' + esc(proposal.total?.suggested_points) + '"> / ' + esc(proposal.total?.max_points) + '</p>'
                    + (proposal.items || []).map((item, i) => '<div class="postbox" style="padding:12px"><h3>' + esc(item.exercise_title || ('Exercice #' + item.exercise_id)) + '</h3><p>Points <input data-file-item-points="' + i + '" type="number" step="0.25" value="' + esc(item.suggested_points) + '"> / ' + esc(item.max_points) + '</p><p><textarea data-file-item-feedback="' + i + '" rows="4" class="large-text">' + esc(item.feedback) + '</textarea></p></div>').join('')
                    + '<p><label>ApprÃ©ciation globale<br><textarea id="ouinpo-file-global-feedback" rows="4" class="large-text">' + esc(proposal.global_feedback) + '</textarea></label></p>'
                    + '<p><button class="button button-primary" id="ouinpo-validate-file-correction">Valider cette correction</button></p>';
                $('ouinpo-file-submission-detail').innerHTML = html;
                $('ouinpo-validate-file-correction').addEventListener('click', async () => {
                    proposal.total.suggested_points = parseFloat($('ouinpo-file-final-total').value || '0');
                    proposal.global_feedback = $('ouinpo-file-global-feedback').value;
                    document.querySelectorAll('[data-file-item-points]').forEach(input => proposal.items[parseInt(input.dataset.fileItemPoints,10)].suggested_points = parseFloat(input.value || '0'));
                    document.querySelectorAll('[data-file-item-feedback]').forEach(input => proposal.items[parseInt(input.dataset.fileItemFeedback,10)].feedback = input.value);
                    try { await postJson(cfg.rest.submissionsUrl + '/' + id + '/validate', {correction: proposal}); await refresh(); notice('success','Correction validÃ©e.'); }
                    catch(e){ notice('error', e.message); }
                });
            }
        })();
        </script>
        <?php
    }
}
