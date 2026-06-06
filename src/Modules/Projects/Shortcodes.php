<?php

namespace Ouinpo\Suite\Modules\Projects;

use Ouinpo\Suite\Core\Capabilities;

defined('ABSPATH') || exit;

final class Shortcodes
{
    private static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        add_shortcode('ouinpo_my_projects', [self::class, 'myProjects']);
        add_shortcode('ouinpo_project_kanban', [self::class, 'kanban']);
        add_shortcode('ouinpo_project_journal', [self::class, 'journal']);
        add_shortcode('ouinpo_project_deliverables', [self::class, 'deliverables']);
        add_shortcode('ouinpo_project_evidence', [self::class, 'evidence']);
        add_shortcode('ouinpo_project_sheet', [self::class, 'sheet']);
        add_shortcode('ouinpo_teacher_projects', [self::class, 'teacherProjects']);
    }

    public static function myProjects($atts = []): string
    {
        unset($atts);

        if (!is_user_logged_in()) {
            return self::notice('Connexion requise pour consulter vos projets.');
        }

        Assets::enqueueFront();

        $repository = new Repository();
        $projects = $repository->listVisibleProjects(get_current_user_id());
        $selectedId = isset($_GET['ouinpo_project_id']) ? absint(wp_unslash($_GET['ouinpo_project_id'])) : 0;
        $selectedView = isset($_GET['ouinpo_project_view']) ? sanitize_key(wp_unslash((string) $_GET['ouinpo_project_view'])) : '';

        ob_start();
        ?>
        <div class="ouinpo-projects-list">
            <h2>SPOPI Projects - Mes projets</h2>
            <?php if (!$projects): ?>
                <p class="ouinpo-projects-empty">Aucun projet pour le moment.</p>
            <?php else: ?>
                <div class="ouinpo-projects-cards">
                    <?php foreach ($projects as $project): ?>
                        <?php
                        $projectId = (int) $project['id'];
                        $kanbanUrl = self::currentUrl(['ouinpo_project_id' => $projectId, 'ouinpo_project_view' => 'kanban']);
                        $journalUrl = self::currentUrl(['ouinpo_project_id' => $projectId, 'ouinpo_project_view' => 'journal']);
                        $deliverablesUrl = self::currentUrl(['ouinpo_project_id' => $projectId, 'ouinpo_project_view' => 'deliverables']);
                        $evidenceUrl = self::currentUrl(['ouinpo_project_id' => $projectId, 'ouinpo_project_view' => 'evidence']);
                        $sheetUrl = self::currentUrl(['ouinpo_project_id' => $projectId, 'ouinpo_project_view' => 'sheet']);
                        ?>
                        <article class="ouinpo-projects-project-card">
                            <h3><?php echo esc_html((string) $project['title']); ?></h3>
                            <p>
                                <span class="ouinpo-projects-badge"><?php echo esc_html((string) $project['status']); ?></span>
                                <?php echo esc_html(self::period($project)); ?>
                            </p>
                            <p><?php echo esc_html((string) ((int) ($project['open_tasks_count'] ?? 0)) . ' tache(s) ouverte(s)'); ?></p>
                            <p class="ouinpo-projects-actions">
                                <a class="ouinpo-projects-button" href="<?php echo esc_url($kanbanUrl); ?>">Kanban</a>
                                <a class="ouinpo-projects-button ouinpo-projects-button-secondary" href="<?php echo esc_url($journalUrl); ?>">Journal</a>
                                <a class="ouinpo-projects-button ouinpo-projects-button-secondary" href="<?php echo esc_url($deliverablesUrl); ?>">Livrables</a>
                                <a class="ouinpo-projects-button ouinpo-projects-button-secondary" href="<?php echo esc_url($evidenceUrl); ?>">Traces</a>
                                <a class="ouinpo-projects-button ouinpo-projects-button-secondary" href="<?php echo esc_url($sheetUrl); ?>">Fiche</a>
                            </p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php

        if ($selectedId > 0 && $repository->userCanViewProject($selectedId, get_current_user_id())) {
            if ($selectedView === 'journal') {
                echo self::journal(['id' => $selectedId]);
            } elseif ($selectedView === 'deliverables') {
                echo self::deliverables(['id' => $selectedId]);
            } elseif ($selectedView === 'evidence') {
                echo self::evidence(['id' => $selectedId]);
            } elseif ($selectedView === 'sheet') {
                echo self::sheet(['id' => $selectedId]);
            } else {
                echo self::kanban(['id' => $selectedId]);
            }
        }

        return (string) ob_get_clean();
    }

    public static function teacherProjects($atts = []): string
    {
        unset($atts);

        if (!is_user_logged_in()) {
            return self::notice('Connexion requise.');
        }

        if (!self::canCreateOrManage()) {
            return self::notice('Vue reservee aux enseignants.');
        }

        Assets::enqueueFront();

        $repository = new Repository();
        $projects = $repository->listVisibleProjects(get_current_user_id());

        ob_start();
        ?>
        <div class="ouinpo-projects-teacher">
            <h2>SPOPI Projects - Suivi enseignant</h2>
            <?php if (!$projects): ?>
                <p class="ouinpo-projects-empty">Aucun projet cree.</p>
            <?php else: ?>
                <div class="ouinpo-projects-table-wrap">
                    <table class="ouinpo-projects-table">
                        <thead>
                        <tr>
                            <th>Projet</th>
                            <th>Statut</th>
                            <th>Membres</th>
                            <th>Taches</th>
                            <th>Livrables</th>
                            <th>Traces</th>
                            <th>Dernier journal</th>
                            <th>Alertes</th>
                            <th>Acces</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($projects as $project): ?>
                            <?php
                            $summary = $repository->getProjectSummary((int) $project['id']) ?: $project;
                            $kanbanUrl = self::currentUrl(['ouinpo_project_id' => (int) $project['id'], 'ouinpo_project_view' => 'kanban']);
                            $sheetUrl = self::currentUrl(['ouinpo_project_id' => (int) $project['id'], 'ouinpo_project_view' => 'sheet']);
                            $alerts = $repository->projectAlerts($summary);
                            ?>
                            <tr>
                                <td><?php echo esc_html((string) $project['title']); ?></td>
                                <td><?php echo esc_html((string) $project['status']); ?></td>
                                <td><?php echo esc_html((string) ((int) ($summary['members_count'] ?? 0))); ?></td>
                                <td><?php echo esc_html((string) ((int) ($summary['done_tasks_count'] ?? 0)) . ' / ' . (int) ($summary['tasks_count'] ?? 0)); ?></td>
                                <td><?php echo esc_html((string) ((int) ($summary['validated_deliverables_count'] ?? 0)) . ' / ' . (int) ($summary['deliverables_count'] ?? 0)); ?></td>
                                <td><?php echo esc_html((string) (($summary['last_evidence_at'] ?? '') ?: '-')); ?></td>
                                <td><?php echo esc_html((string) (($summary['last_log_at'] ?? '') ?: '-')); ?></td>
                                <td><?php echo esc_html($alerts ? implode(', ', $alerts) : '-'); ?></td>
                                <td>
                                    <a class="ouinpo-projects-button" href="<?php echo esc_url($kanbanUrl); ?>">Kanban</a>
                                    <a class="ouinpo-projects-button ouinpo-projects-button-secondary" href="<?php echo esc_url($sheetUrl); ?>">Fiche</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php

        $selectedId = isset($_GET['ouinpo_project_id']) ? absint(wp_unslash($_GET['ouinpo_project_id'])) : 0;
        if ($selectedId > 0 && $repository->userCanViewProject($selectedId, get_current_user_id())) {
            $selectedView = isset($_GET['ouinpo_project_view']) ? sanitize_key(wp_unslash((string) $_GET['ouinpo_project_view'])) : '';
            echo $selectedView === 'sheet' ? self::sheet(['id' => $selectedId]) : self::kanban(['id' => $selectedId]);
        }

        return (string) ob_get_clean();
    }

    public static function kanban($atts = []): string
    {
        if (!is_user_logged_in()) {
            return self::notice('Connexion requise pour consulter le Kanban.');
        }

        $atts = shortcode_atts(['id' => 0], (array) $atts, 'ouinpo_project_kanban');
        $projectId = absint($atts['id'] ?: (isset($_GET['ouinpo_project_id']) ? wp_unslash($_GET['ouinpo_project_id']) : 0));

        if ($projectId <= 0) {
            return self::notice('Projet non precise.');
        }

        $repository = new Repository();
        if (!$repository->userCanViewProject($projectId, get_current_user_id())) {
            return self::notice('Acces refuse a ce projet.');
        }

        Assets::enqueueFront();

        $project = $repository->getProjectSummary($projectId);
        if (!$project) {
            return self::notice('Projet introuvable.');
        }

        $columns = $repository->getBoard($projectId);
        $members = $repository->getMembers($projectId);
        $canCreateTask = $repository->userCanManageProject($projectId, get_current_user_id())
            || current_user_can(Capabilities::PROJECTS_EDIT_OWN_TASKS)
            || current_user_can('manage_options');

        ob_start();
        ?>
        <section class="ouinpo-projects-kanban" data-ouinpo-projects-board data-project-id="<?php echo esc_attr((string) $projectId); ?>" data-can-edit="<?php echo esc_attr($canCreateTask ? '1' : '0'); ?>">
            <div class="ouinpo-projects-heading">
                <div>
                    <h2><?php echo esc_html((string) $project['title']); ?></h2>
                    <p><?php echo esc_html((string) ($project['description'] ? wp_strip_all_tags((string) $project['description']) : 'Bureau des Pataprojets Applicatifs')); ?></p>
                </div>
                <span class="ouinpo-projects-badge"><?php echo esc_html((string) $project['status']); ?></span>
            </div>

            <?php if ($canCreateTask): ?>
                <form class="ouinpo-projects-task-form" data-ouinpo-projects-task-form>
                    <label>
                        <span>Titre</span>
                        <input type="text" name="title" required maxlength="190">
                    </label>
                    <label>
                        <span>Description</span>
                        <textarea name="description" rows="2"></textarea>
                    </label>
                    <label>
                        <span>Priorite</span>
                        <select name="priority">
                            <?php foreach (Repository::PRIORITIES as $priority): ?>
                                <option value="<?php echo esc_attr($priority); ?>"><?php echo esc_html($priority); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Assigne</span>
                        <select name="assigned_user_id">
                            <option value="">Non assigne</option>
                            <?php foreach ($members as $member): ?>
                                <option value="<?php echo esc_attr((string) $member['user_id']); ?>">
                                    <?php echo esc_html((string) ($member['display_name'] ?: $member['user_email'])); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Echeance</span>
                        <input type="date" name="due_date">
                    </label>
                    <button type="submit" class="ouinpo-projects-button">Creer une tache</button>
                </form>
            <?php endif; ?>

            <div class="ouinpo-projects-board" data-ouinpo-projects-columns>
                <?php echo self::renderColumns($columns, $canCreateTask); ?>
            </div>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    public static function journal($atts = []): string
    {
        if (!is_user_logged_in()) {
            return self::notice('Connexion requise pour consulter le journal.');
        }

        $atts = shortcode_atts(['id' => 0], (array) $atts, 'ouinpo_project_journal');
        $projectId = absint($atts['id'] ?: (isset($_GET['ouinpo_project_id']) ? wp_unslash($_GET['ouinpo_project_id']) : 0));

        if ($projectId <= 0) {
            return self::notice('Projet non precise.');
        }

        $repository = new Repository();
        if (!$repository->userCanViewProject($projectId, get_current_user_id())) {
            return self::notice('Acces refuse a ce journal.');
        }

        Assets::enqueueFront();

        $project = $repository->getProject($projectId);
        $logs = $repository->getLogs($projectId);
        $canAdd = current_user_can(Capabilities::PROJECTS_COMMENT)
            || $repository->userCanManageProject($projectId, get_current_user_id())
            || current_user_can('manage_options');

        ob_start();
        ?>
        <section class="ouinpo-projects-journal" data-ouinpo-projects-journal data-project-id="<?php echo esc_attr((string) $projectId); ?>">
            <div class="ouinpo-projects-heading">
                <div>
                    <h2>Journal - <?php echo esc_html((string) ($project['title'] ?? 'Projet')); ?></h2>
                    <p>Bureau des Pataprojets Applicatifs</p>
                </div>
            </div>

            <?php if ($canAdd): ?>
                <form class="ouinpo-projects-journal-form" data-ouinpo-projects-journal-form>
                    <label>
                        <span>Travail realise *</span>
                        <textarea name="work_done" rows="3" required></textarea>
                    </label>
                    <label>
                        <span>Blocage</span>
                        <textarea name="blockers" rows="2"></textarea>
                    </label>
                    <label>
                        <span>Decision prise</span>
                        <textarea name="decision_taken" rows="2"></textarea>
                    </label>
                    <label>
                        <span>Prochaine etape</span>
                        <textarea name="next_step" rows="2"></textarea>
                    </label>
                    <button type="submit" class="ouinpo-projects-button">Ajouter au journal</button>
                </form>
            <?php endif; ?>

            <div class="ouinpo-projects-log-list">
                <?php if (!$logs): ?>
                    <p class="ouinpo-projects-empty">Aucune entree pour le moment.</p>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <article class="ouinpo-projects-log">
                            <header>
                                <strong><?php echo esc_html((string) ($log['display_name'] ?: 'Utilisateur')); ?></strong>
                                <time><?php echo esc_html((string) $log['created_at']); ?></time>
                            </header>
                            <div><?php echo wp_kses_post(wpautop((string) $log['work_done'])); ?></div>
                            <?php foreach (['blockers' => 'Blocage', 'decision_taken' => 'Decision', 'next_step' => 'Suite'] as $field => $label): ?>
                                <?php if (!empty($log[$field])): ?>
                                    <p><strong><?php echo esc_html($label); ?> :</strong> <?php echo wp_kses_post((string) $log[$field]); ?></p>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    public static function deliverables($atts = []): string
    {
        if (!is_user_logged_in()) {
            return self::notice('Connexion requise pour consulter les livrables.');
        }

        $atts = shortcode_atts(['id' => 0], (array) $atts, 'ouinpo_project_deliverables');
        $projectId = absint($atts['id'] ?: (isset($_GET['ouinpo_project_id']) ? wp_unslash($_GET['ouinpo_project_id']) : 0));

        if ($projectId <= 0) {
            return self::notice('Projet non precise.');
        }

        $repository = new Repository();
        if (!$repository->userCanViewProject($projectId, get_current_user_id())) {
            return self::notice('Acces refuse a ces livrables.');
        }

        Assets::enqueueFront();

        $project = $repository->getProject($projectId);
        $deliverables = $repository->getDeliverables($projectId);
        $canManage = $repository->userCanManageProject($projectId, get_current_user_id());

        ob_start();
        ?>
        <section class="ouinpo-projects-deliverables" data-ouinpo-projects-deliverables data-project-id="<?php echo esc_attr((string) $projectId); ?>">
            <div class="ouinpo-projects-heading">
                <div>
                    <h2>Livrables - <?php echo esc_html((string) ($project['title'] ?? 'Projet')); ?></h2>
                    <p>Bureau des Pataprojets Applicatifs</p>
                </div>
            </div>

            <?php if ($canManage): ?>
                <form class="ouinpo-projects-deliverable-form" data-ouinpo-projects-deliverable-form>
                    <label><span>Titre</span><input type="text" name="title" required maxlength="190"></label>
                    <label>
                        <span>Type</span>
                        <select name="type">
                            <?php foreach (Repository::DELIVERABLE_TYPES as $type): ?>
                                <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($type); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Echeance</span><input type="date" name="due_date"></label>
                    <label class="ouinpo-projects-form-wide"><span>Description</span><textarea name="description" rows="2"></textarea></label>
                    <button type="submit" class="ouinpo-projects-button">Ajouter le livrable</button>
                </form>
            <?php endif; ?>

            <?php if (!$deliverables): ?>
                <p class="ouinpo-projects-empty">Aucun livrable pour le moment.</p>
            <?php else: ?>
                <div class="ouinpo-projects-table-wrap">
                    <table class="ouinpo-projects-table">
                        <thead><tr><th>Livrable</th><th>Type</th><th>Statut</th><th>Echeance</th><th>Validation</th><?php if ($canManage): ?><th>Actions</th><?php endif; ?></tr></thead>
                        <tbody>
                        <?php foreach ($deliverables as $deliverable): ?>
                            <tr data-deliverable-id="<?php echo esc_attr((string) $deliverable['id']); ?>">
                                <td>
                                    <strong><?php echo esc_html((string) $deliverable['title']); ?></strong>
                                    <?php if (!empty($deliverable['description'])): ?>
                                        <div><?php echo wp_kses_post(wpautop((string) $deliverable['description'])); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html((string) $deliverable['type']); ?></td>
                                <td><span class="ouinpo-projects-badge ouinpo-projects-status-<?php echo esc_attr((string) $deliverable['status']); ?>"><?php echo esc_html((string) $deliverable['status']); ?></span></td>
                                <td><?php echo esc_html((string) ($deliverable['due_date'] ?: '-')); ?></td>
                                <td><?php echo esc_html((string) ($deliverable['validated_at'] ?: '-')); ?></td>
                                <?php if ($canManage): ?>
                                    <td class="ouinpo-projects-actions">
                                        <button type="button" data-ouinpo-projects-deliverable-status="validated">Valider</button>
                                        <button type="button" data-ouinpo-projects-deliverable-status="needs_revision">A reprendre</button>
                                        <button type="button" data-ouinpo-projects-deliverable-delete>Supprimer</button>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    public static function evidence($atts = []): string
    {
        if (!is_user_logged_in()) {
            return self::notice('Connexion requise pour consulter les traces.');
        }

        $atts = shortcode_atts(['id' => 0], (array) $atts, 'ouinpo_project_evidence');
        $projectId = absint($atts['id'] ?: (isset($_GET['ouinpo_project_id']) ? wp_unslash($_GET['ouinpo_project_id']) : 0));

        if ($projectId <= 0) {
            return self::notice('Projet non precise.');
        }

        $repository = new Repository();
        if (!$repository->userCanViewProject($projectId, get_current_user_id())) {
            return self::notice('Acces refuse a ces traces.');
        }

        Assets::enqueueFront();

        $project = $repository->getProject($projectId);
        $evidence = $repository->getEvidence($projectId);
        $deliverables = $repository->getDeliverables($projectId);
        $tasks = self::flattenBoardTasks($repository->getBoard($projectId));
        $canAdd = $repository->userCanSubmitProjectItem($projectId, get_current_user_id());

        ob_start();
        ?>
        <section class="ouinpo-projects-evidence" data-ouinpo-projects-evidence data-project-id="<?php echo esc_attr((string) $projectId); ?>">
            <div class="ouinpo-projects-heading">
                <div>
                    <h2>Traces - <?php echo esc_html((string) ($project['title'] ?? 'Projet')); ?></h2>
                    <p>Liens, captures, documents et preuves de travail</p>
                </div>
            </div>

            <?php if ($canAdd): ?>
                <form class="ouinpo-projects-evidence-form" data-ouinpo-projects-evidence-form>
                    <label><span>Titre</span><input type="text" name="title" required maxlength="190"></label>
                    <label>
                        <span>Type</span>
                        <select name="evidence_type">
                            <?php foreach (Repository::EVIDENCE_TYPES as $type): ?>
                                <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($type); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>URL</span><input type="url" name="url"></label>
                    <label>
                        <span>Livrable</span>
                        <select name="deliverable_id">
                            <option value="">Aucun</option>
                            <?php foreach ($deliverables as $deliverable): ?>
                                <option value="<?php echo esc_attr((string) $deliverable['id']); ?>"><?php echo esc_html((string) $deliverable['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Tache</span>
                        <select name="task_id">
                            <option value="">Aucune</option>
                            <?php foreach ($tasks as $task): ?>
                                <option value="<?php echo esc_attr((string) $task['id']); ?>"><?php echo esc_html((string) $task['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="ouinpo-projects-form-wide"><span>Description</span><textarea name="description" rows="2"></textarea></label>
                    <button type="submit" class="ouinpo-projects-button">Ajouter une trace</button>
                </form>
            <?php endif; ?>

            <div class="ouinpo-projects-cards">
                <?php if (!$evidence): ?>
                    <p class="ouinpo-projects-empty">Aucune trace pour le moment.</p>
                <?php endif; ?>
                <?php foreach ($evidence as $item): ?>
                    <article class="ouinpo-projects-evidence-card" data-evidence-id="<?php echo esc_attr((string) $item['id']); ?>">
                        <h3><?php echo esc_html((string) $item['title']); ?></h3>
                        <p><span class="ouinpo-projects-badge"><?php echo esc_html((string) $item['evidence_type']); ?></span> <?php echo esc_html((string) $item['created_at']); ?></p>
                        <p><?php echo esc_html((string) ($item['display_name'] ?: 'Utilisateur')); ?></p>
                        <?php if (!empty($item['deliverable_title'])): ?><p>Livrable : <?php echo esc_html((string) $item['deliverable_title']); ?></p><?php endif; ?>
                        <?php if (!empty($item['task_title'])): ?><p>Tache : <?php echo esc_html((string) $item['task_title']); ?></p><?php endif; ?>
                        <?php if (!empty($item['description'])): ?><div><?php echo wp_kses_post(wpautop((string) $item['description'])); ?></div><?php endif; ?>
                        <?php if (!empty($item['url'])): ?><p><a href="<?php echo esc_url((string) $item['url']); ?>" rel="nofollow noopener">Ouvrir la trace</a></p><?php endif; ?>
                        <?php if ($repository->userCanManageEvidenceItem($item, get_current_user_id())): ?>
                            <button type="button" data-ouinpo-projects-evidence-delete>Supprimer</button>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    public static function sheet($atts = []): string
    {
        if (!is_user_logged_in()) {
            return self::notice('Connexion requise pour consulter la fiche projet.');
        }

        $atts = shortcode_atts(['id' => 0], (array) $atts, 'ouinpo_project_sheet');
        $projectId = absint($atts['id'] ?: (isset($_GET['ouinpo_project_id']) ? wp_unslash($_GET['ouinpo_project_id']) : 0));

        if ($projectId <= 0) {
            return self::notice('Projet non precise.');
        }

        $repository = new Repository();
        if (!$repository->userCanViewProject($projectId, get_current_user_id())) {
            return self::notice('Acces refuse a cette fiche.');
        }

        Assets::enqueueFront();

        $project = $repository->getProjectSummary($projectId);
        if (!$project) {
            return self::notice('Projet introuvable.');
        }

        $members = $repository->getMembers($projectId);
        $deliverables = $repository->getDeliverables($projectId);
        $tasks = $repository->getMainTasks($projectId);
        $logs = array_slice($repository->getLogs($projectId), 0, 6);
        $evidence = array_slice($repository->getEvidence($projectId), 0, 10);
        $competencies = $repository->getCompetencyLinks($projectId);

        ob_start();
        ?>
        <section class="ouinpo-projects-sheet">
            <div class="ouinpo-projects-sheet-toolbar">
                <button type="button" class="ouinpo-projects-button" data-ouinpo-projects-print>Imprimer</button>
            </div>
            <header class="ouinpo-projects-sheet-header">
                <p>SPOPI Projects - Bureau des Pataprojets Applicatifs</p>
                <h2><?php echo esc_html((string) $project['title']); ?></h2>
                <p><?php echo esc_html(self::period($project)); ?> - <?php echo esc_html((string) $project['status']); ?></p>
            </header>

            <div class="ouinpo-projects-sheet-grid">
                <section>
                    <h3>Contexte</h3>
                    <div><?php echo wp_kses_post(wpautop((string) ($project['description'] ?: ''))); ?></div>
                    <p>Niveau : <?php echo esc_html((string) ($project['level'] ?: '-')); ?></p>
                    <p>Classe : <?php echo esc_html((string) ($project['class_slug'] ?: '-')); ?></p>
                </section>
                <section>
                    <h3>Equipe</h3>
                    <?php echo self::renderSimpleList(array_map(static function (array $member): string {
                        return (string) (($member['display_name'] ?: $member['user_email']) . ' - ' . $member['role']);
                    }, $members)); ?>
                </section>
                <section>
                    <h3>Ce que le projet permet de demontrer</h3>
                    <?php echo self::renderCompetencyList($competencies); ?>
                </section>
                <section>
                    <h3>Indicateurs</h3>
                    <p>Taches : <?php echo esc_html((string) ((int) $project['done_tasks_count'] . ' faites / ' . (int) $project['tasks_count'])); ?></p>
                    <p>Livrables : <?php echo esc_html((string) ((int) $project['validated_deliverables_count'] . ' valides / ' . (int) $project['deliverables_count'])); ?></p>
                    <p>Derniere trace : <?php echo esc_html((string) ($project['last_evidence_at'] ?: '-')); ?></p>
                </section>
            </div>

            <section><h3>Livrables</h3><?php echo self::renderDeliverablesTable($deliverables); ?></section>
            <section><h3>Taches principales</h3><?php echo self::renderTasksTable($tasks); ?></section>
            <section><h3>Journal recent</h3><?php echo self::renderLogs($logs); ?></section>
            <section><h3>Traces</h3><?php echo self::renderEvidenceList($evidence); ?></section>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    public static function renderColumns(array $columns, bool $canEdit): string
    {
        ob_start();

        foreach ($columns as $index => $column) {
            $tasks = is_array($column['tasks'] ?? null) ? $column['tasks'] : [];
            ?>
            <section class="ouinpo-projects-column" data-column-id="<?php echo esc_attr((string) $column['id']); ?>">
                <h3><?php echo esc_html((string) $column['title']); ?></h3>
                <div class="ouinpo-projects-task-list">
                    <?php if (!$tasks): ?>
                        <p class="ouinpo-projects-empty">Rien ici.</p>
                    <?php endif; ?>
                    <?php foreach ($tasks as $task): ?>
                        <article class="ouinpo-projects-task" data-task-id="<?php echo esc_attr((string) $task['id']); ?>">
                            <header>
                                <strong><?php echo esc_html((string) $task['title']); ?></strong>
                                <span class="ouinpo-projects-priority ouinpo-projects-priority-<?php echo esc_attr((string) $task['priority']); ?>">
                                    <?php echo esc_html((string) $task['priority']); ?>
                                </span>
                            </header>
                            <?php if (!empty($task['description'])): ?>
                                <div class="ouinpo-projects-task-description"><?php echo wp_kses_post(wpautop((string) $task['description'])); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($task['due_date'])): ?>
                                <p class="ouinpo-projects-due">Echeance : <?php echo esc_html((string) $task['due_date']); ?></p>
                            <?php endif; ?>
                            <?php if ($canEdit): ?>
                                <div class="ouinpo-projects-task-actions">
                                    <button type="button" data-ouinpo-projects-move="-1" <?php disabled($index === 0); ?>>&larr;</button>
                                    <button type="button" data-ouinpo-projects-edit>Modifier</button>
                                    <button type="button" data-ouinpo-projects-delete>Archiver</button>
                                    <button type="button" data-ouinpo-projects-move="1" <?php disabled($index === count($columns) - 1); ?>>&rarr;</button>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php
        }

        return (string) ob_get_clean();
    }

    private static function flattenBoardTasks(array $columns): array
    {
        $tasks = [];

        foreach ($columns as $column) {
            foreach ((array) ($column['tasks'] ?? []) as $task) {
                $tasks[] = $task;
            }
        }

        return $tasks;
    }

    private static function renderSimpleList(array $items): string
    {
        if (!$items) {
            return '<p class="ouinpo-projects-empty">Aucun element.</p>';
        }

        ob_start();
        ?>
        <ul class="ouinpo-projects-simple-list">
            <?php foreach ($items as $item): ?>
                <li><?php echo esc_html((string) $item); ?></li>
            <?php endforeach; ?>
        </ul>
        <?php

        return (string) ob_get_clean();
    }

    private static function renderCompetencyList(array $competencies): string
    {
        if (!$competencies) {
            return '<p class="ouinpo-projects-empty">Aucune competence liee pour le moment.</p>';
        }

        $labels = [];
        foreach ($competencies as $competency) {
            $label = (string) ($competency['label'] ?: $competency['competency'] ?: ('Competence #' . (int) $competency['competency_id']));
            $domain = (string) ($competency['domain'] ?? '');
            $labels[] = $domain !== '' ? $domain . ' - ' . wp_strip_all_tags($label) : wp_strip_all_tags($label);
        }

        return self::renderSimpleList(array_values(array_unique($labels)));
    }

    private static function renderDeliverablesTable(array $deliverables): string
    {
        if (!$deliverables) {
            return '<p class="ouinpo-projects-empty">Aucun livrable.</p>';
        }

        ob_start();
        ?>
        <table class="ouinpo-projects-table">
            <thead><tr><th>Livrable</th><th>Type</th><th>Statut</th><th>Echeance</th></tr></thead>
            <tbody>
            <?php foreach ($deliverables as $deliverable): ?>
                <tr>
                    <td><?php echo esc_html((string) $deliverable['title']); ?></td>
                    <td><?php echo esc_html((string) $deliverable['type']); ?></td>
                    <td><?php echo esc_html((string) $deliverable['status']); ?></td>
                    <td><?php echo esc_html((string) ($deliverable['due_date'] ?: '-')); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php

        return (string) ob_get_clean();
    }

    private static function renderTasksTable(array $tasks): string
    {
        if (!$tasks) {
            return '<p class="ouinpo-projects-empty">Aucune tache.</p>';
        }

        ob_start();
        ?>
        <table class="ouinpo-projects-table">
            <thead><tr><th>Tache</th><th>Priorite</th><th>Statut</th><th>Echeance</th></tr></thead>
            <tbody>
            <?php foreach ($tasks as $task): ?>
                <tr>
                    <td><?php echo esc_html((string) $task['title']); ?></td>
                    <td><?php echo esc_html((string) $task['priority']); ?></td>
                    <td><?php echo esc_html((string) $task['status']); ?></td>
                    <td><?php echo esc_html((string) ($task['due_date'] ?: '-')); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php

        return (string) ob_get_clean();
    }

    private static function renderLogs(array $logs): string
    {
        if (!$logs) {
            return '<p class="ouinpo-projects-empty">Aucune entree.</p>';
        }

        ob_start();
        foreach ($logs as $log) {
            ?>
            <article class="ouinpo-projects-log">
                <header><strong><?php echo esc_html((string) ($log['display_name'] ?: 'Utilisateur')); ?></strong><time><?php echo esc_html((string) $log['created_at']); ?></time></header>
                <div><?php echo wp_kses_post(wpautop((string) $log['work_done'])); ?></div>
            </article>
            <?php
        }

        return (string) ob_get_clean();
    }

    private static function renderEvidenceList(array $evidence): string
    {
        if (!$evidence) {
            return '<p class="ouinpo-projects-empty">Aucune trace.</p>';
        }

        ob_start();
        ?>
        <ul class="ouinpo-projects-simple-list">
            <?php foreach ($evidence as $item): ?>
                <li>
                    <?php echo esc_html((string) $item['title']); ?>
                    <?php if (!empty($item['url'])): ?>
                        - <a href="<?php echo esc_url((string) $item['url']); ?>" rel="nofollow noopener">lien</a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php

        return (string) ob_get_clean();
    }

    private static function notice(string $message): string
    {
        return '<div class="ouinpo-projects-notice">' . esc_html($message) . '</div>';
    }

    private static function period(array $project): string
    {
        $start = (string) ($project['start_date'] ?? '');
        $end = (string) ($project['end_date'] ?? '');

        if ($start !== '' && $end !== '') {
            return $start . ' - ' . $end;
        }

        if ($start !== '') {
            return 'Depuis ' . $start;
        }

        if ($end !== '') {
            return 'Jusqu au ' . $end;
        }

        return 'Periode non definie';
    }

    private static function currentUrl(array $args): string
    {
        $url = get_permalink();
        if (!$url) {
            $url = home_url(add_query_arg([]));
        }

        return add_query_arg($args, $url);
    }

    private static function canCreateOrManage(): bool
    {
        return current_user_can(Capabilities::PROJECTS_CREATE)
            || current_user_can(Capabilities::PROJECTS_MANAGE_CLASS)
            || current_user_can(Capabilities::PROJECTS_MANAGE_ALL)
            || current_user_can('manage_options');
    }
}
