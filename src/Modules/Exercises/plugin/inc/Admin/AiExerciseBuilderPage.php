<?php

namespace Ouinpo\Exercises\Admin;

use Ouinpo\Exercises\Services\ExerciseInsertService;
use Ouinpo\Suite\Core\AiSettings;
use Ouinpo\Suite\Core\Capabilities;

defined('ABSPATH') || exit;

final class AiExerciseBuilderPage
{
    private static function table(string $suffix): string
    {
        global $wpdb;

        return $wpdb->prefix . 'ouin_exo_' . $suffix;
    }

    public static function render(): void
    {
        if (!current_user_can(Capabilities::MANAGE_EXERCISES) && !current_user_can('manage_options')) {
            wp_die('Accès refusé.');
        }

        $data = self::load_reference_data();
        $rest = [
            'generateUrl' => rest_url('ouinpo/v1/ai-exercise-builder/generate'),
            'createUrl' => rest_url('ouinpo/v1/ai-exercise-builder/create'),
            'nonce' => wp_create_nonce('wp_rest'),
            'aiEnabled' => AiSettings::enabled_for_usage('pedagogical_suggestions'),
            'disabledMessage' => AiSettings::get('ouinpo_ai_disabled_message'),
        ];

        ?>
        <div class="wrap">
            <h1>Créer un exercice avec l’IA</h1>

            <?php if (!$rest['aiEnabled']): ?>
                <div class="notice notice-warning"><p><?php echo esc_html((string) $rest['disabledMessage']); ?></p></div>
            <?php endif; ?>

            <div id="ouinpo-ai-exercise-builder" class="ouinpo-admin-ai-builder">
                <div class="postbox ouinpo-admin-postbox">
                    <h2 class="ouinpo-admin-heading-topless">Paramètres de génération</h2>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="ouinpo-ai-level">Niveau</label></th>
                                <td>
                                    <select id="ouinpo-ai-level">
                                        <?php foreach ($data['levels'] as $level): ?>
                                            <option value="<?php echo (int) $level['id']; ?>"><?php echo esc_html((string) $level['label']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ouinpo-ai-domain">Domaine BO</label></th>
                                <td>
                                    <select id="ouinpo-ai-domain">
                                        <?php foreach ($data['domains'] as $domain): ?>
                                            <option value="<?php echo esc_attr((string) $domain['slug']); ?>"><?php echo esc_html((string) $domain['label']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Compétences BO</th>
                                <td>
                                    <div id="ouinpo-ai-competencies" class="ouinpo-ai-competencies"></div>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ouinpo-ai-difficulty">Difficulté</label></th>
                                <td>
                                    <select id="ouinpo-ai-difficulty">
                                        <?php foreach ($data['difficulties'] as $difficulty): ?>
                                            <option value="<?php echo esc_attr((string) $difficulty['slug']); ?>"><?php echo esc_html((string) $difficulty['label']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ouinpo-ai-type">Type d’exercice</label></th>
                                <td>
                                    <select id="ouinpo-ai-type">
                                        <?php foreach (ExerciseInsertService::exercise_types() as $value => $label): ?>
                                            <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ouinpo-ai-duration">Durée estimée</label></th>
                                <td><input id="ouinpo-ai-duration" type="number" min="1" max="240" value="20"> minutes</td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="ouinpo-ai-free-prompt">Consigne libre</label></th>
                                <td><textarea id="ouinpo-ai-free-prompt" rows="4" class="large-text"></textarea></td>
                            </tr>
                        </tbody>
                    </table>
                    <p>
                        <button type="button" class="button button-primary" data-ai-action="generate">Générer une proposition</button>
                        <button type="button" class="button" data-ai-action="regenerate">Régénérer</button>
                        <button type="button" class="button" data-ai-action="variant">Variante</button>
                        <button type="button" class="button" data-ai-action="simplify">Simplifier</button>
                        <button type="button" class="button" data-ai-action="harder">Rendre plus difficile</button>
                    </p>
                    <div id="ouinpo-ai-builder-status" aria-live="polite"></div>
                </div>

                <div id="ouinpo-ai-preview" class="postbox ouinpo-admin-postbox" hidden>
                    <h2 class="ouinpo-admin-heading-topless">Prévisualisation éditable</h2>
                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr><th scope="row"><label for="ouinpo-ai-title">Titre</label></th><td><input id="ouinpo-ai-title" type="text" class="large-text"></td></tr>
                            <tr><th scope="row"><label for="ouinpo-ai-slug">Slug</label></th><td><input id="ouinpo-ai-slug" type="text" class="regular-text"></td></tr>
                            <tr><th scope="row"><label for="ouinpo-ai-statement">Énoncé HTML</label></th><td><textarea id="ouinpo-ai-statement" rows="12" class="large-text code"></textarea></td></tr>
                            <tr><th scope="row"><label for="ouinpo-ai-hint-1">Indice 1</label></th><td><textarea id="ouinpo-ai-hint-1" rows="3" class="large-text"></textarea></td></tr>
                            <tr><th scope="row"><label for="ouinpo-ai-hint-2">Indice 2</label></th><td><textarea id="ouinpo-ai-hint-2" rows="3" class="large-text"></textarea></td></tr>
                            <tr><th scope="row"><label for="ouinpo-ai-hint-3">Indice 3</label></th><td><textarea id="ouinpo-ai-hint-3" rows="3" class="large-text"></textarea></td></tr>
                            <tr><th scope="row"><label for="ouinpo-ai-solution">Solution HTML</label></th><td><textarea id="ouinpo-ai-solution" rows="12" class="large-text code"></textarea></td></tr>
                            <tr><th scope="row"><label for="ouinpo-ai-rationale">Justification pédagogique</label></th><td><textarea id="ouinpo-ai-rationale" rows="3" class="large-text"></textarea></td></tr>
                        </tbody>
                    </table>
                    <p>
                        <button type="button" class="button button-primary" id="ouinpo-ai-create">Créer l’exercice</button>
                    </p>
                    <div id="ouinpo-ai-create-result"></div>
                </div>
            </div>
        </div>

        <style>
            .ouinpo-ai-competencies { max-height: 260px; overflow: auto; border: 1px solid #ccd0d4; background: #fff; padding: 8px; }
            .ouinpo-ai-competencies label { display: block; margin: 0 0 8px; }
            .ouinpo-admin-ai-builder .postbox { padding: 12px; }
            #ouinpo-ai-builder-status .notice, #ouinpo-ai-create-result .notice { margin: 12px 0 0; }
        </style>

        <script>
        window.ouinpoAiExerciseBuilder = <?php echo wp_json_encode(['data' => $data, 'rest' => $rest], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        (function(){
            const cfg = window.ouinpoAiExerciseBuilder;
            const $ = (id) => document.getElementById(id);
            let proposal = null;

            function notice(target, type, message) {
                target.innerHTML = '<div class="notice notice-' + type + '"><p>' + escapeHtml(message) + '</p></div>';
            }

            function escapeHtml(value) {
                return String(value || '').replace(/[&<>"']/g, function (char) {
                    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[char];
                });
            }

            function selectedCompetencies() {
                return Array.from(document.querySelectorAll('#ouinpo-ai-competencies input:checked')).map(function(input){
                    return parseInt(input.value, 10);
                }).filter(Boolean);
            }

            function renderCompetencies() {
                const domain = $('ouinpo-ai-domain').value;
                const levelId = parseInt($('ouinpo-ai-level').value, 10);
                const level = cfg.data.levels.find(function(item){ return parseInt(item.id, 10) === levelId; });
                const levelSlug = level ? String(level.slug || '').toLowerCase() : '';
                const box = $('ouinpo-ai-competencies');
                const items = cfg.data.competencies.filter(function(item){
                    const sameDomain = String(item.domain_slug || '') === domain;
                    const compLevel = String(item.level_key || item.level || '').toLowerCase();
                    return sameDomain && (compLevel === '' || compLevel === levelSlug || compLevel === 'transversal');
                });

                if (!items.length) {
                    box.innerHTML = '<p>Aucune compétence active pour ce domaine et ce niveau.</p>';
                    return;
                }

                box.innerHTML = items.map(function(item){
                    return '<label><input type="checkbox" value="' + parseInt(item.id, 10) + '"> ' + escapeHtml(item.label || item.competency || '') + '</label>';
                }).join('');
            }

            function requestPayload(action) {
                return {
                    level_id: parseInt($('ouinpo-ai-level').value, 10),
                    domain_slug: $('ouinpo-ai-domain').value,
                    competency_ids: selectedCompetencies(),
                    difficulty_slug: $('ouinpo-ai-difficulty').value,
                    exercise_type: $('ouinpo-ai-type').value,
                    estimated_minutes: parseInt($('ouinpo-ai-duration').value, 10) || 20,
                    free_prompt: $('ouinpo-ai-free-prompt').value,
                    action: action,
                    previous: collectEditedProposal() || proposal || {}
                };
            }

            async function postJson(url, payload) {
                const response = await fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': cfg.rest.nonce
                    },
                    body: JSON.stringify(payload)
                });
                const data = await response.json().catch(function(){ return {}; });
                if (!response.ok) {
                    throw new Error(data.message || 'Erreur REST.');
                }
                return data;
            }

            function fillPreview(nextProposal) {
                proposal = nextProposal;
                $('ouinpo-ai-title').value = proposal.title || '';
                $('ouinpo-ai-slug').value = proposal.slug || '';
                $('ouinpo-ai-statement').value = proposal.statement_html || '';
                $('ouinpo-ai-solution').value = proposal.solution_html || '';
                $('ouinpo-ai-rationale').value = proposal.pedagogical_rationale || '';
                [1,2,3].forEach(function(rank){
                    const hint = (proposal.hints || []).find(function(item){ return parseInt(item.rank, 10) === rank; }) || {};
                    $('ouinpo-ai-hint-' + rank).value = hint.html || '';
                });
                $('ouinpo-ai-preview').hidden = false;
            }

            function collectEditedProposal() {
                if (!proposal) {
                    return null;
                }
                return Object.assign({}, proposal, {
                    title: $('ouinpo-ai-title').value,
                    slug: $('ouinpo-ai-slug').value,
                    statement_html: $('ouinpo-ai-statement').value,
                    solution_html: $('ouinpo-ai-solution').value,
                    pedagogical_rationale: $('ouinpo-ai-rationale').value,
                    estimated_minutes: parseInt($('ouinpo-ai-duration').value, 10) || proposal.estimated_minutes || 20,
                    hints: [1,2,3].map(function(rank){
                        return {rank: rank, html: $('ouinpo-ai-hint-' + rank).value};
                    })
                });
            }

            async function generate(action) {
                const status = $('ouinpo-ai-builder-status');
                if (!cfg.rest.aiEnabled) {
                    notice(status, 'warning', cfg.rest.disabledMessage || 'IA désactivée.');
                    return;
                }
                if (!selectedCompetencies().length) {
                    notice(status, 'error', 'Sélectionne au moins une compétence.');
                    return;
                }

                status.innerHTML = '<p>Génération en cours…</p>';
                try {
                    const data = await postJson(cfg.rest.generateUrl, requestPayload(action));
                    fillPreview(data.proposal);
                    notice(status, 'success', 'Proposition générée. Relis et modifie avant création.');
                } catch (error) {
                    notice(status, 'error', error.message);
                }
            }

            async function createExercise() {
                const result = $('ouinpo-ai-create-result');
                const edited = collectEditedProposal();
                if (!edited) {
                    notice(result, 'error', 'Aucune proposition à créer.');
                    return;
                }

                result.innerHTML = '<p>Création en cours…</p>';
                try {
                    const data = await postJson(cfg.rest.createUrl, {proposal: edited});
                    result.innerHTML = '<div class="notice notice-success"><p>Exercice créé : ID ' + parseInt(data.exercise_id, 10)
                        + ' · <a href="' + escapeHtml(data.edit_url) + '">Éditer</a>'
                        + ' · <a href="' + escapeHtml(data.public_url) + '">Voir</a></p></div>';
                } catch (error) {
                    notice(result, 'error', error.message);
                }
            }

            $('ouinpo-ai-domain').addEventListener('change', renderCompetencies);
            $('ouinpo-ai-level').addEventListener('change', renderCompetencies);
            document.querySelectorAll('[data-ai-action]').forEach(function(button){
                button.addEventListener('click', function(){ generate(button.getAttribute('data-ai-action') || 'generate'); });
            });
            $('ouinpo-ai-create').addEventListener('click', createExercise);
            renderCompetencies();
        })();
        </script>
        <?php
    }

    private static function load_reference_data(): array
    {
        global $wpdb;

        $p = $wpdb->prefix . 'ouin_exo_';

        $levels = $wpdb->get_results("SELECT id, slug, label FROM {$p}school_levels ORDER BY sort_order ASC, id ASC", ARRAY_A) ?: [];
        $difficulties = $wpdb->get_results("SELECT id, slug, label FROM {$p}difficulties ORDER BY id ASC", ARRAY_A) ?: [];
        $competencies = $wpdb->get_results("
            SELECT id, domain_id, domain, domain_slug, competency, label, level, track, slug
            FROM {$p}competencies
            WHERE active = 1
            ORDER BY domain, level, id
        ", ARRAY_A) ?: [];

        $domains = [];
        foreach ($competencies as &$competency) {
            $slug = (string) ($competency['domain_slug'] ?? '');
            if ($slug === '') {
                $slug = sanitize_title((string) ($competency['domain'] ?? ''));
            }
            if ($slug === '') {
                continue;
            }
            $competency['domain_slug'] = $slug;
            $competency['level_key'] = sanitize_title((string) ($competency['level'] ?? ''));
            $domains[$slug] = [
                'slug' => $slug,
                'label' => (string) ($competency['domain'] ?? $slug),
            ];
        }
        unset($competency);

        uasort($domains, static function (array $a, array $b): int {
            return strnatcasecmp((string) $a['label'], (string) $b['label']);
        });

        return [
            'levels' => array_values($levels),
            'difficulties' => array_values($difficulties),
            'domains' => array_values($domains),
            'competencies' => array_values($competencies),
        ];
    }
}
