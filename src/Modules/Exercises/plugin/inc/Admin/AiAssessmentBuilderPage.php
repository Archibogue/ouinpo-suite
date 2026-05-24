<?php

namespace Ouinpo\Exercises\Admin;

use Ouinpo\Suite\Core\AiSettings;

defined('ABSPATH') || exit;

final class AiAssessmentBuilderPage
{
    public static function render_panel(array $context): void
    {
        $filters = (array) ($context['filters'] ?? []);
        $exercises = (array) ($context['exercises'] ?? []);
        $difficulties = (array) ($context['difficulties'] ?? []);
        $ai_enabled = AiSettings::enabled_for_usage('pedagogical_suggestions');

        $exercise_options = array_map(static function ($exo): array {
            return [
                'id' => (int) $exo->id,
                'title' => (string) $exo->title,
            ];
        }, $exercises);

        $config = [
            'rest' => [
                'kpiUrl' => rest_url('ouinpo/v1/ai-assessment-builder/kpi'),
                'generateUrl' => rest_url('ouinpo/v1/ai-assessment-builder/generate'),
                'createExerciseUrl' => rest_url('ouinpo/v1/ai-exercise-builder/create'),
                'generateExerciseUrl' => rest_url('ouinpo/v1/ai-exercise-builder/generate'),
                'nonce' => wp_create_nonce('wp_rest'),
                'aiEnabled' => $ai_enabled,
                'disabledMessage' => AiSettings::get('ouinpo_ai_disabled_message'),
            ],
            'filters' => [
                'group_id' => (int) ($filters['group_id'] ?? 0),
                'level_id' => (int) ($filters['level_id'] ?? 0),
                'target_minutes' => (int) ($filters['target_minutes'] ?? 90),
                'domain_slugs' => array_values((array) ($filters['domain_slugs'] ?? [])),
                'competency_ids' => array_values(array_map('intval', (array) ($filters['competency_ids'] ?? []))),
            ],
            'exercises' => $exercise_options,
        ];
        ?>
        <div class="ouinpo-builder-card" id="ouinpo-ai-assessment-builder">
            <h2>Composer avec l’IA</h2>
            <?php if (!$ai_enabled): ?>
                <div class="notice notice-warning inline"><p><?php echo esc_html((string) $config['rest']['disabledMessage']); ?></p></div>
            <?php endif; ?>

            <div class="ouinpo-builder-field">
                <label for="ouinpo-ai-assessment-count">Nombre d’exercices</label>
                <input type="number" id="ouinpo-ai-assessment-count" min="1" max="12" value="4">
            </div>

            <div class="ouinpo-builder-field">
                <label for="ouinpo-ai-assessment-difficulty">Difficulté globale</label>
                <select id="ouinpo-ai-assessment-difficulty">
                    <?php foreach ($difficulties as $difficulty): ?>
                        <option value="<?php echo esc_attr((string) $difficulty->slug); ?>"><?php echo esc_html((string) $difficulty->label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="ouinpo-builder-field">
                <label for="ouinpo-ai-existing-ratio">Part d’exercices existants (%)</label>
                <input type="number" id="ouinpo-ai-existing-ratio" min="0" max="100" step="10" value="70">
            </div>

            <div class="ouinpo-builder-field">
                <label for="ouinpo-ai-new-ratio">Part de nouveaux exercices IA (%)</label>
                <input type="number" id="ouinpo-ai-new-ratio" min="0" max="100" step="10" value="30">
            </div>

            <div class="ouinpo-builder-field">
                <label for="ouinpo-ai-assessment-constraints">Contraintes libres</label>
                <textarea id="ouinpo-ai-assessment-constraints" rows="3"></textarea>
            </div>

            <p>
                <button type="button" class="button" id="ouinpo-ai-load-kpi">Afficher le rappel KPI</button>
                <button type="button" class="button button-primary" id="ouinpo-ai-generate-assessment">Générer une proposition</button>
            </p>

            <div id="ouinpo-ai-assessment-status" aria-live="polite"></div>
            <div id="ouinpo-ai-kpi-summary"></div>
            <div id="ouinpo-ai-assessment-proposal"></div>
        </div>

        <style>
            #ouinpo-ai-assessment-proposal table { margin-top: 12px; }
            #ouinpo-ai-assessment-proposal textarea { width: 100%; }
            .ouinpo-ai-row-actions { display: flex; gap: 6px; flex-wrap: wrap; }
        </style>

        <script>
        window.ouinpoAiAssessmentBuilder = <?php echo wp_json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        (function(){
            const cfg = window.ouinpoAiAssessmentBuilder;
            const $ = (id) => document.getElementById(id);
            let proposal = null;

            function esc(value) {
                return String(value || '').replace(/[&<>"']/g, function (char) {
                    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char];
                });
            }

            function notice(target, type, message) {
                target.innerHTML = '<div class="notice notice-' + type + ' inline"><p>' + esc(message) + '</p></div>';
            }

            function multiValues(selector) {
                const node = document.querySelector(selector);
                return node ? Array.from(node.selectedOptions).map(function(option){ return option.value; }).filter(Boolean) : [];
            }

            function payload() {
                return {
                    group_id: parseInt(document.querySelector('[name="group_id"]')?.value || cfg.filters.group_id || 0, 10),
                    level_id: parseInt(document.querySelector('[name="level_id"]')?.value || cfg.filters.level_id || 0, 10),
                    target_minutes: parseInt(document.querySelector('[name="target_minutes"]')?.value || cfg.filters.target_minutes || 90, 10),
                    items_count: parseInt($('ouinpo-ai-assessment-count').value || 4, 10),
                    domain_slugs: multiValues('[name="domain_slugs[]"]'),
                    competency_ids: multiValues('[name="competency_ids[]"]').map(function(v){ return parseInt(v, 10); }).filter(Boolean),
                    difficulty_slug: $('ouinpo-ai-assessment-difficulty').value,
                    existing_ratio: parseInt($('ouinpo-ai-existing-ratio').value || 70, 10),
                    new_ratio: parseInt($('ouinpo-ai-new-ratio').value || 30, 10),
                    free_constraints: $('ouinpo-ai-assessment-constraints').value
                };
            }

            async function postJson(url, body) {
                const response = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {'Content-Type': 'application/json', 'X-WP-Nonce': cfg.rest.nonce},
                    body: JSON.stringify(body)
                });
                const data = await response.json().catch(function(){ return {}; });
                if (!response.ok) {
                    throw new Error(data.message || 'Erreur REST.');
                }
                return data;
            }

            function renderKpi(kpi) {
                const alerts = (kpi.alerts || []).map(function(alert){
                    return '<li>' + esc(alert.message || alert.label) + '</li>';
                }).join('');
                $('ouinpo-ai-kpi-summary').innerHTML =
                    '<div class="notice notice-info inline"><p><strong>KPI classe :</strong> '
                    + esc(kpi.availability || 'KPI partiel')
                    + ' · DS trouvés : ' + parseInt(kpi.summary?.total_assessments_found || 0, 10)
                    + ' · exercices vus : ' + parseInt(kpi.summary?.total_exercises_seen || 0, 10)
                    + ' · tentatives : ' + parseInt(kpi.summary?.total_attempts || 0, 10)
                    + '</p>' + (alerts ? '<ul>' + alerts + '</ul>' : '') + '</div>';
            }

            function renderProposal(nextProposal) {
                proposal = nextProposal;
                const rows = (proposal.items || []).map(function(item, index){
                    const isNew = item.kind === 'new_ai_exercise_request';
                    const req = item.exercise_request || {};
                    const draft = item.exercise_draft || null;
                    const title = isNew ? (draft?.title || req.title_hint || 'Nouvel exercice IA') : item.title;
                    const domains = isNew ? (draft ? (draft.domains || []).map(function(d){ return d.label || ''; }) : (req.domain_slugs || [])) : (item.domain_labels || []);
                    const comps = isNew ? (draft ? (draft.competencies || []).map(function(c){ return c.label || ''; }) : (req.competency_ids || []).map(function(id){ return '#' + id; })) : (item.competency_labels || []);
                    const preview = draft ? '<details><summary>Prévisualiser</summary><p><strong>Énoncé</strong></p><div>' + esc(draft.statement_html || '') + '</div><p><strong>Solution</strong></p><div>' + esc(draft.solution_html || '') + '</div></details>' : '<small>' + esc(req.teacher_prompt || '') + '</small>';
                    return '<tr data-index="' + index + '">'
                        + '<td><input type="number" class="ouinpo-ai-order" min="1" value="' + (index + 1) + '"></td>'
                        + '<td><strong>' + esc(title) + '</strong><br><small>' + esc(item.rationale || req.rationale || '') + '</small>' + (isNew ? '<br>' + preview : '') + '</td>'
                        + '<td>' + esc(domains.join(', ')) + '</td>'
                        + '<td>' + esc(comps.join(', ')) + '</td>'
                        + '<td>' + esc(item.difficulty || draft?.difficulty || req.difficulty || '') + '</td>'
                        + '<td><input type="number" class="ouinpo-ai-minutes" min="1" value="' + parseInt(item.estimated_minutes || draft?.estimated_minutes || req.estimated_minutes || 20, 10) + '"></td>'
                        + '<td><input type="number" class="ouinpo-ai-points" min="0" step="0.25" value="' + Number(item.suggested_points || 5) + '"></td>'
                        + '<td>' + (isNew ? (draft ? 'Brouillon IA à valider' : 'À générer') : 'Existant #' + parseInt(item.exercise_id, 10)) + '</td>'
                        + '<td><div class="ouinpo-ai-row-actions">'
                        + (isNew ? '<button type="button" class="button button-small" data-generate-draft="' + index + '">' + (draft ? 'Regénérer' : 'Générer') + '</button>' + (draft ? '<button type="button" class="button button-small button-primary" data-create-draft="' + index + '">Créer après validation</button>' : '') : '')
                        + '<select data-replace="' + index + '"><option value="">Remplacer...</option>' + cfg.exercises.map(function(ex){ return '<option value="' + ex.id + '">#' + ex.id + ' ' + esc(ex.title) + '</option>'; }).join('') + '</select>'
                        + '<button type="button" class="button button-small" data-remove="' + index + '">Supprimer</button>'
                        + '</div></td>'
                        + '</tr>';
                }).join('');

                $('ouinpo-ai-assessment-proposal').innerHTML =
                    '<h3>' + esc(proposal.title || 'Proposition de devoir') + '</h3>'
                    + '<p>' + esc(proposal.global_rationale || '') + '</p>'
                    + (proposal.warnings?.length ? '<div class="notice notice-warning inline"><p>' + esc(proposal.warnings.join(' ')) + '</p></div>' : '')
                    + '<table class="widefat striped"><thead><tr><th>Ordre</th><th>Titre</th><th>Domaine</th><th>Compétences</th><th>Difficulté</th><th>Durée</th><th>Points</th><th>Source</th><th>Actions</th></tr></thead><tbody>' + rows + '</tbody></table>'
                    + '<p><select id="ouinpo-ai-add-existing"><option value="">Ajouter un exercice existant...</option>' + cfg.exercises.map(function(ex){ return '<option value="' + ex.id + '">#' + ex.id + ' ' + esc(ex.title) + '</option>'; }).join('') + '</select> <button type="button" class="button" id="ouinpo-ai-add-existing-button">Ajouter</button></p>'
                    + '<p><button type="button" class="button button-primary" id="ouinpo-ai-apply-assessment">Appliquer au panier existant</button></p>';
            }

            async function loadKpi() {
                try {
                    notice($('ouinpo-ai-assessment-status'), 'info', 'Calcul KPI en cours…');
                    const data = await postJson(cfg.rest.kpiUrl, payload());
                    renderKpi(data.kpi);
                    $('ouinpo-ai-assessment-status').innerHTML = '';
                } catch (error) {
                    $('ouinpo-ai-assessment-status').innerHTML = '<div class="notice notice-error inline"><p>' + esc(error.message) + '</p><p><button type="button" class="button" id="ouinpo-ai-retry-assessment">Relancer</button></p></div>';
                }
            }

            async function generate() {
                if (!cfg.rest.aiEnabled) {
                    notice($('ouinpo-ai-assessment-status'), 'warning', cfg.rest.disabledMessage || 'IA désactivée.');
                    return;
                }
                try {
                    notice($('ouinpo-ai-assessment-status'), 'info', 'Génération du devoir en cours…');
                    const data = await postJson(cfg.rest.generateUrl, payload());
                    renderKpi(data.proposal.kpi || {});
                    renderProposal(data.proposal);
                    notice($('ouinpo-ai-assessment-status'), 'success', 'Proposition générée. Valide chaque brouillon IA avant de créer le devoir.');
                } catch (error) {
                    notice($('ouinpo-ai-assessment-status'), 'error', error.message);
                }
            }

            async function createDraft(index) {
                const item = proposal?.items?.[index];
                if (!item || item.kind !== 'new_ai_exercise_request' || !item.exercise_draft) {
                    return;
                }
                try {
                    const data = await postJson(cfg.rest.createExerciseUrl, {proposal: item.exercise_draft});
                    item.kind = 'existing_exercise';
                    item.exercise_id = parseInt(data.exercise_id, 10);
                    item.title = item.exercise_draft.title;
                    delete item.exercise_draft;
                    renderProposal(proposal);
                    notice($('ouinpo-ai-assessment-status'), 'success', 'Exercice IA créé et prêt à être ajouté au devoir.');
                } catch (error) {
                    notice($('ouinpo-ai-assessment-status'), 'error', error.message);
                }
            }

            async function regenerateDraft(index) {
                const item = proposal?.items?.[index];
                if (!item || item.kind !== 'new_ai_exercise_request') {
                    return;
                }
                try {
                    const body = payload();
                    const req = item.exercise_request || {};
                    const data = await postJson(cfg.rest.generateExerciseUrl, {
                        level_id: req.level_id || body.level_id,
                        domain_slug: (req.domain_slugs || [])[0] || body.domain_slugs[0] || '',
                        competency_ids: req.competency_ids || body.competency_ids,
                        difficulty_slug: req.difficulty_slug || body.difficulty_slug,
                        exercise_type: 'classic',
                        estimated_minutes: item.exercise_draft?.estimated_minutes || req.estimated_minutes || 20,
                        free_prompt: req.teacher_prompt || body.free_constraints,
                        action: item.exercise_draft ? 'variant' : 'generate',
                        previous: item.exercise_draft || {}
                    });
                    item.exercise_draft = data.proposal;
                    item.suggested_points = item.suggested_points || 5;
                    renderProposal(proposal);
                    notice($('ouinpo-ai-assessment-status'), 'success', 'Brouillon généré. Relis la prévisualisation avant de créer l’exercice.');
                } catch (error) {
                    notice($('ouinpo-ai-assessment-status'), 'error', error.message);
                }
            }

            function addExistingExercise() {
                const select = document.getElementById('ouinpo-ai-add-existing');
                if (!select || !select.value || !proposal) {
                    return;
                }
                const ex = cfg.exercises.find(function(item){ return parseInt(item.id, 10) === parseInt(select.value, 10); });
                proposal.items.push({
                    kind: 'existing_exercise',
                    exercise_id: parseInt(select.value, 10),
                    title: ex ? ex.title : 'Exercice #' + select.value,
                    domain_labels: [],
                    competency_labels: [],
                    difficulty: '',
                    estimated_minutes: 20,
                    suggested_points: 5,
                    rationale: 'Ajouté manuellement.'
                });
                renderProposal(proposal);
            }

            function applyToBasket() {
                const form = document.getElementById('ouinpo-builder-create-form');
                if (!form || !proposal) {
                    return;
                }
                form.querySelectorAll('.ouinpo-ai-hidden').forEach(function(node){ node.remove(); });
                document.querySelectorAll('.ouinpo-builder-check').forEach(function(input){ input.checked = false; });

                Array.from(document.querySelectorAll('#ouinpo-ai-assessment-proposal tbody tr')).forEach(function(row){
                    const index = parseInt(row.getAttribute('data-index'), 10);
                    const item = proposal.items[index];
                    if (!item || item.kind !== 'existing_exercise') {
                        return;
                    }
                    const id = parseInt(item.exercise_id, 10);
                    const points = row.querySelector('.ouinpo-ai-points')?.value || item.suggested_points || 5;
                    const order = row.querySelector('.ouinpo-ai-order')?.value || (index + 1);
                    const checkbox = document.querySelector('.ouinpo-builder-check[value="' + id + '"]');
                    if (checkbox) {
                        checkbox.checked = true;
                        const p = document.querySelector('[name="points[' + id + ']"]');
                        const o = document.querySelector('[name="sort_order[' + id + ']"]');
                        if (p) p.value = points;
                        if (o) o.value = order;
                    } else {
                        [['exercise_ids[]', id], ['points[' + id + ']', points], ['sort_order[' + id + ']', order]].forEach(function(pair){
                            const input = document.createElement('input');
                            input.type = 'hidden';
                            input.className = 'ouinpo-ai-hidden';
                            input.name = pair[0];
                            input.value = pair[1];
                            form.appendChild(input);
                        });
                    }
                });
                const title = document.getElementById('assessment-title');
                if (title && proposal.title) title.value = proposal.title;
                notice($('ouinpo-ai-assessment-status'), 'success', 'Panier mis à jour. Tu peux maintenant créer le DS avec la sélection.');
            }

            $('ouinpo-ai-load-kpi').addEventListener('click', loadKpi);
            $('ouinpo-ai-generate-assessment').addEventListener('click', generate);
            $('ouinpo-ai-assessment-proposal').addEventListener('click', function(event){
                const createIndex = event.target.getAttribute('data-create-draft');
                const regenerateIndex = event.target.getAttribute('data-generate-draft');
                const removeIndex = event.target.getAttribute('data-remove');
                if (createIndex !== null) createDraft(parseInt(createIndex, 10));
                if (regenerateIndex !== null) regenerateDraft(parseInt(regenerateIndex, 10));
                if (removeIndex !== null && proposal) {
                    proposal.items.splice(parseInt(removeIndex, 10), 1);
                    renderProposal(proposal);
                }
                if (event.target.id === 'ouinpo-ai-apply-assessment') applyToBasket();
                if (event.target.id === 'ouinpo-ai-add-existing-button') addExistingExercise();
            });
            $('ouinpo-ai-assessment-proposal').addEventListener('change', function(event){
                const index = event.target.getAttribute('data-replace');
                if (index !== null && event.target.value && proposal) {
                    const ex = cfg.exercises.find(function(item){ return parseInt(item.id, 10) === parseInt(event.target.value, 10); });
                    proposal.items[parseInt(index, 10)] = {
                        kind: 'existing_exercise',
                        exercise_id: parseInt(event.target.value, 10),
                        title: ex ? ex.title : 'Exercice #' + event.target.value,
                        domain_labels: [],
                        competency_labels: [],
                        difficulty: '',
                        estimated_minutes: 20,
                        suggested_points: 5,
                        rationale: 'Remplacé manuellement.'
                    };
                    renderProposal(proposal);
                }
            });
            $('ouinpo-ai-assessment-status').addEventListener('click', function(event){
                if (event.target.id === 'ouinpo-ai-retry-assessment') {
                    generate();
                }
            });
        })();
        </script>
        <?php
    }
}
