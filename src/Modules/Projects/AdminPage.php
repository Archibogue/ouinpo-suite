<?php

namespace Ouinpo\Suite\Modules\Projects;

use Ouinpo\Suite\Core\Capabilities;

defined('ABSPATH') || exit;

final class AdminPage
{
    private const PAGE = 'ouinpo-projects';
    private const NONCE_ACTION = 'ouinpo_projects_admin';
    private const NONCE_NAME = 'ouinpo_projects_admin_nonce';

    private static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        add_action('admin_menu', [self::class, 'registerMenu'], 20);
    }

    public static function registerMenu(): void
    {
        $parent = defined('OUINPO_SUITE_ADMIN_SLUG') ? OUINPO_SUITE_ADMIN_SLUG : 'ouinpo-suite';

        add_submenu_page(
            $parent,
            'OuinPo Projects',
            'Projects',
            Capabilities::PROJECTS_CREATE,
            self::PAGE,
            [self::class, 'render']
        );
    }

    public static function render(): void
    {
        if (!self::canManage()) {
            wp_die('Acces refuse.');
        }

        $repository = new Repository();

        $requestMethod = isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : '';

        if ($requestMethod === 'POST') {
            self::handlePost($repository);
        }

        $projectId = isset($_GET['project_id']) ? absint(wp_unslash($_GET['project_id'])) : 0;
        $project = $projectId > 0 ? $repository->getProject($projectId) : null;
        $projects = $repository->listVisibleProjects(get_current_user_id());
        ?>
        <div class="wrap ouinpo-projects-admin">
            <h1>SPOPI Projects - Bureau des Pataprojets Applicatifs</h1>

            <div class="ouinpo-projects-admin-grid">
                <div class="ouinpo-projects-admin-card">
                    <h2><?php echo esc_html($project ? 'Modifier le projet' : 'Creer un projet'); ?></h2>
                    <?php self::projectForm($project); ?>
                </div>

                <?php if ($project): ?>
                    <div class="ouinpo-projects-admin-card">
                        <h2>Membres</h2>
                        <?php self::membersPanel($repository, (int) $project['id']); ?>
                    </div>
                    <div class="ouinpo-projects-admin-card">
                        <h2>Livrables</h2>
                        <?php self::deliverablesPanel($repository, (int) $project['id']); ?>
                    </div>
                    <div class="ouinpo-projects-admin-card">
                        <h2>Competences</h2>
                        <?php self::competenciesPanel($repository, (int) $project['id']); ?>
                    </div>
                    <div class="ouinpo-projects-admin-card">
                        <h2>Traces et exports</h2>
                        <?php self::evidencePanel($repository, (int) $project['id']); ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="ouinpo-projects-admin-card">
                <h2>Projets</h2>
                <?php self::projectsTable($projects); ?>
            </div>
        </div>
        <?php
    }

    private static function handlePost(Repository $repository): void
    {
        if (
            !isset($_POST[self::NONCE_NAME])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST[self::NONCE_NAME])), self::NONCE_ACTION)
        ) {
            echo '<div class="notice notice-error"><p>Action refusee : nonce invalide.</p></div>';
            return;
        }

        $action = isset($_POST['ouinpo_projects_action'])
            ? sanitize_key(wp_unslash((string) $_POST['ouinpo_projects_action']))
            : '';

        if ($action === 'save_project') {
            $projectId = isset($_POST['project_id']) ? absint(wp_unslash($_POST['project_id'])) : 0;
            $data = self::postedProjectData();

            if ($projectId > 0) {
                if (!$repository->userCanManageProject($projectId, get_current_user_id())) {
                    wp_die('Acces refuse.');
                }
                $repository->updateProject($projectId, $data);
                echo '<div class="notice notice-success is-dismissible"><p>Projet mis a jour.</p></div>';
            } else {
                $newId = $repository->createProject($data, get_current_user_id());
                if ($newId > 0) {
                    echo '<div class="notice notice-success is-dismissible"><p>Projet cree avec ses colonnes par defaut.</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Creation impossible : titre obligatoire.</p></div>';
                }
            }
        } elseif ($action === 'add_member') {
            $projectId = isset($_POST['project_id']) ? absint(wp_unslash($_POST['project_id'])) : 0;
            if (!$repository->userCanManageProject($projectId, get_current_user_id())) {
                wp_die('Acces refuse.');
            }
            $userId = isset($_POST['user_id']) ? absint(wp_unslash($_POST['user_id'])) : 0;
            $role = isset($_POST['role']) ? sanitize_key(wp_unslash((string) $_POST['role'])) : 'member';
            if ($userId > 0) {
                $repository->addMember($projectId, $userId, $role);
                echo '<div class="notice notice-success is-dismissible"><p>Membre ajoute.</p></div>';
            }
        } elseif ($action === 'remove_member') {
            $projectId = isset($_POST['project_id']) ? absint(wp_unslash($_POST['project_id'])) : 0;
            if (!$repository->userCanManageProject($projectId, get_current_user_id())) {
                wp_die('Acces refuse.');
            }
            $userId = isset($_POST['user_id']) ? absint(wp_unslash($_POST['user_id'])) : 0;
            $repository->removeMember($projectId, $userId);
            echo '<div class="notice notice-success is-dismissible"><p>Membre retire.</p></div>';
        } elseif ($action === 'create_default_deliverables') {
            $projectId = isset($_POST['project_id']) ? absint(wp_unslash($_POST['project_id'])) : 0;
            if (!$repository->userCanManageProject($projectId, get_current_user_id())) {
                wp_die('Acces refuse.');
            }
            $created = $repository->ensureDefaultDeliverables($projectId, get_current_user_id());
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html((string) $created) . ' livrable(s) cree(s).</p></div>';
        } elseif ($action === 'save_deliverable') {
            $projectId = isset($_POST['project_id']) ? absint(wp_unslash($_POST['project_id'])) : 0;
            if (!$repository->userCanManageProject($projectId, get_current_user_id())) {
                wp_die('Acces refuse.');
            }
            $deliverableId = $repository->createDeliverable($projectId, self::postedDeliverableData(), get_current_user_id());
            if ($deliverableId > 0) {
                echo '<div class="notice notice-success is-dismissible"><p>Livrable ajoute.</p></div>';
            }
        } elseif ($action === 'set_deliverable_status') {
            $deliverableId = isset($_POST['deliverable_id']) ? absint(wp_unslash($_POST['deliverable_id'])) : 0;
            $deliverable = $repository->getDeliverable($deliverableId);
            if (!$deliverable || !$repository->userCanManageProject((int) $deliverable['project_id'], get_current_user_id())) {
                wp_die('Acces refuse.');
            }
            $status = isset($_POST['status']) ? sanitize_key(wp_unslash((string) $_POST['status'])) : 'expected';
            if ($status === 'validated' && !current_user_can(Capabilities::PROJECTS_VALIDATE) && !current_user_can('manage_options')) {
                wp_die('Validation refusee.');
            }
            $repository->updateDeliverableStatus($deliverableId, $status, get_current_user_id());
            echo '<div class="notice notice-success is-dismissible"><p>Statut du livrable mis a jour.</p></div>';
        } elseif ($action === 'delete_deliverable') {
            $deliverableId = isset($_POST['deliverable_id']) ? absint(wp_unslash($_POST['deliverable_id'])) : 0;
            $deliverable = $repository->getDeliverable($deliverableId);
            if (!$deliverable || !$repository->userCanManageProject((int) $deliverable['project_id'], get_current_user_id())) {
                wp_die('Acces refuse.');
            }
            $repository->deleteDeliverable($deliverableId);
            echo '<div class="notice notice-success is-dismissible"><p>Livrable supprime.</p></div>';
        } elseif ($action === 'add_competency_link') {
            $projectId = isset($_POST['project_id']) ? absint(wp_unslash($_POST['project_id'])) : 0;
            if (!$repository->userCanManageProject($projectId, get_current_user_id())) {
                wp_die('Acces refuse.');
            }
            $objectType = isset($_POST['object_type']) ? sanitize_key(wp_unslash((string) $_POST['object_type'])) : 'project';
            $objectId = isset($_POST['object_id']) ? absint(wp_unslash($_POST['object_id'])) : $projectId;
            $competencyId = isset($_POST['competency_id']) ? absint(wp_unslash($_POST['competency_id'])) : 0;
            $repository->addCompetencyLink($projectId, $objectType, $objectId ?: $projectId, $competencyId, get_current_user_id());
            echo '<div class="notice notice-success is-dismissible"><p>Lien competence enregistre.</p></div>';
        } elseif ($action === 'delete_competency_link') {
            $linkId = isset($_POST['link_id']) ? absint(wp_unslash($_POST['link_id'])) : 0;
            $link = $repository->getCompetencyLink($linkId);
            if (!$link || !$repository->userCanManageProject((int) $link['project_id'], get_current_user_id())) {
                wp_die('Acces refuse.');
            }
            $repository->deleteCompetencyLink($linkId);
            echo '<div class="notice notice-success is-dismissible"><p>Lien competence supprime.</p></div>';
        }
    }

    private static function projectForm(?array $project): void
    {
        $projectId = $project ? (int) $project['id'] : 0;
        ?>
        <form method="post">
            <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
            <input type="hidden" name="ouinpo_projects_action" value="save_project">
            <input type="hidden" name="project_id" value="<?php echo esc_attr((string) $projectId); ?>">

            <table class="form-table" role="presentation">
                <tbody>
                <tr>
                    <th scope="row"><label for="ouinpo-project-title">Titre</label></th>
                    <td><input id="ouinpo-project-title" class="regular-text" type="text" name="title" required value="<?php echo esc_attr((string) ($project['title'] ?? '')); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ouinpo-project-slug">Slug</label></th>
                    <td><input id="ouinpo-project-slug" class="regular-text" type="text" name="slug" value="<?php echo esc_attr((string) ($project['slug'] ?? '')); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ouinpo-project-description">Description</label></th>
                    <td><textarea id="ouinpo-project-description" class="large-text" rows="4" name="description"><?php echo esc_textarea((string) ($project['description'] ?? '')); ?></textarea></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ouinpo-project-level">Niveau</label></th>
                    <td><input id="ouinpo-project-level" class="regular-text" type="text" name="level" value="<?php echo esc_attr((string) ($project['level'] ?? '')); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ouinpo-project-class">Classe</label></th>
                    <td><input id="ouinpo-project-class" class="regular-text" type="text" name="class_slug" value="<?php echo esc_attr((string) ($project['class_slug'] ?? '')); ?>"></td>
                </tr>
                <tr>
                    <th scope="row"><label for="ouinpo-project-status">Statut</label></th>
                    <td>
                        <select id="ouinpo-project-status" name="status">
                            <?php foreach (Repository::STATUS as $status): ?>
                                <option value="<?php echo esc_attr($status); ?>" <?php selected((string) ($project['status'] ?? 'draft'), $status); ?>>
                                    <?php echo esc_html($status); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="ouinpo-project-teacher">Enseignant</label></th>
                    <td><?php self::userSelect('teacher_id', (int) ($project['teacher_id'] ?? get_current_user_id()), 'ouinpo-project-teacher'); ?></td>
                </tr>
                <tr>
                    <th scope="row">Periode</th>
                    <td>
                        <input type="date" name="start_date" value="<?php echo esc_attr((string) ($project['start_date'] ?? '')); ?>">
                        <input type="date" name="end_date" value="<?php echo esc_attr((string) ($project['end_date'] ?? '')); ?>">
                    </td>
                </tr>
                <tr>
                    <th scope="row">IA eleve</th>
                    <td>
                        <label>
                            <input type="checkbox" name="student_ai_enabled" value="1" <?php checked(1, (int) ($project['student_ai_enabled'] ?? 0)); ?>>
                            Autoriser les membres du projet a preparer leurs brouillons portfolio avec l assistant IA eleve.
                        </label>
                        <p class="description">Necessite aussi l activation globale dans les reglages IA. L assistant eleve ne modifie jamais le projet.</p>
                    </td>
                </tr>
                </tbody>
            </table>

            <?php submit_button($project ? 'Enregistrer le projet' : 'Creer le projet'); ?>
        </form>
        <?php if ($project): ?>
            <p>
                Shortcodes :
                <code>[ouinpo_project_kanban id="<?php echo esc_html((string) $projectId); ?>"]</code>
                <code>[ouinpo_project_journal id="<?php echo esc_html((string) $projectId); ?>"]</code>
                <code>[ouinpo_project_deliverables id="<?php echo esc_html((string) $projectId); ?>"]</code>
                <code>[ouinpo_project_evidence id="<?php echo esc_html((string) $projectId); ?>"]</code>
                <code>[ouinpo_project_sheet id="<?php echo esc_html((string) $projectId); ?>"]</code>
                <code>[ouinpo_project_bts_situation id="<?php echo esc_html((string) $projectId); ?>"]</code>
                <code>[ouinpo_project_student_ai id="<?php echo esc_html((string) $projectId); ?>"]</code>
            </p>
        <?php endif; ?>
        <?php
    }

    private static function membersPanel(Repository $repository, int $projectId): void
    {
        $members = $repository->getMembers($projectId);
        ?>
        <form method="post" class="ouinpo-projects-admin-member-form">
            <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
            <input type="hidden" name="ouinpo_projects_action" value="add_member">
            <input type="hidden" name="project_id" value="<?php echo esc_attr((string) $projectId); ?>">
            <?php self::userSelect('user_id', 0, 'ouinpo-project-member'); ?>
            <select name="role">
                <?php foreach (Repository::MEMBER_ROLES as $role): ?>
                    <option value="<?php echo esc_attr($role); ?>"><?php echo esc_html($role); ?></option>
                <?php endforeach; ?>
            </select>
            <button class="button" type="submit">Ajouter</button>
        </form>

        <?php if (!$members): ?>
            <p>Aucun membre.</p>
        <?php else: ?>
            <table class="widefat striped">
                <thead><tr><th>Utilisateur</th><th>Role</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($members as $member): ?>
                    <tr>
                        <td><?php echo esc_html((string) ($member['display_name'] ?: $member['user_email'])); ?></td>
                        <td><?php echo esc_html((string) $member['role']); ?></td>
                        <td>
                            <form method="post">
                                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                                <input type="hidden" name="ouinpo_projects_action" value="remove_member">
                                <input type="hidden" name="project_id" value="<?php echo esc_attr((string) $projectId); ?>">
                                <input type="hidden" name="user_id" value="<?php echo esc_attr((string) $member['user_id']); ?>">
                                <button class="button-link-delete" type="submit">Retirer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php
    }

    private static function deliverablesPanel(Repository $repository, int $projectId): void
    {
        $deliverables = $repository->getDeliverables($projectId);
        ?>
        <form method="post">
            <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
            <input type="hidden" name="ouinpo_projects_action" value="create_default_deliverables">
            <input type="hidden" name="project_id" value="<?php echo esc_attr((string) $projectId); ?>">
            <button class="button" type="submit">Creer les livrables BTS par defaut</button>
        </form>

        <form method="post" class="ouinpo-projects-admin-member-form">
            <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
            <input type="hidden" name="ouinpo_projects_action" value="save_deliverable">
            <input type="hidden" name="project_id" value="<?php echo esc_attr((string) $projectId); ?>">
            <input type="text" name="title" placeholder="Titre du livrable" required>
            <select name="type">
                <?php foreach (Repository::DELIVERABLE_TYPES as $type): ?>
                    <option value="<?php echo esc_attr($type); ?>"><?php echo esc_html($type); ?></option>
                <?php endforeach; ?>
            </select>
            <input type="date" name="due_date">
            <button class="button" type="submit">Ajouter</button>
        </form>

        <?php if (!$deliverables): ?>
            <p>Aucun livrable.</p>
        <?php else: ?>
            <table class="widefat striped">
                <thead><tr><th>Livrable</th><th>Type</th><th>Statut</th><th>Echeance</th><th>Actions</th></tr></thead>
                <tbody>
                <?php foreach ($deliverables as $deliverable): ?>
                    <tr>
                        <td><?php echo esc_html((string) $deliverable['title']); ?></td>
                        <td><?php echo esc_html((string) $deliverable['type']); ?></td>
                        <td><?php echo esc_html((string) $deliverable['status']); ?></td>
                        <td><?php echo esc_html((string) ($deliverable['due_date'] ?: '-')); ?></td>
                        <td>
                            <form method="post" class="ouinpo-projects-admin-inline">
                                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                                <input type="hidden" name="ouinpo_projects_action" value="set_deliverable_status">
                                <input type="hidden" name="deliverable_id" value="<?php echo esc_attr((string) $deliverable['id']); ?>">
                                <input type="hidden" name="status" value="validated">
                                <button class="button-link" type="submit">Valider</button>
                            </form>
                            <form method="post" class="ouinpo-projects-admin-inline">
                                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                                <input type="hidden" name="ouinpo_projects_action" value="set_deliverable_status">
                                <input type="hidden" name="deliverable_id" value="<?php echo esc_attr((string) $deliverable['id']); ?>">
                                <input type="hidden" name="status" value="needs_revision">
                                <button class="button-link" type="submit">A reprendre</button>
                            </form>
                            <form method="post" class="ouinpo-projects-admin-inline">
                                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                                <input type="hidden" name="ouinpo_projects_action" value="set_deliverable_status">
                                <input type="hidden" name="deliverable_id" value="<?php echo esc_attr((string) $deliverable['id']); ?>">
                                <input type="hidden" name="status" value="rejected">
                                <button class="button-link" type="submit">Rejeter</button>
                            </form>
                            <form method="post" class="ouinpo-projects-admin-inline">
                                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                                <input type="hidden" name="ouinpo_projects_action" value="delete_deliverable">
                                <input type="hidden" name="deliverable_id" value="<?php echo esc_attr((string) $deliverable['id']); ?>">
                                <button class="button-link-delete" type="submit">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php
    }

    private static function competenciesPanel(Repository $repository, int $projectId): void
    {
        $competencies = $repository->getAvailableCompetencies();
        $links = $repository->getCompetencyLinks($projectId);
        $deliverables = $repository->getDeliverables($projectId);
        ?>
        <?php if (!$competencies): ?>
            <p>Aucune competence BO disponible. Importe ou cree le referentiel depuis le module Exercices.</p>
        <?php else: ?>
            <form method="post" class="ouinpo-projects-admin-member-form">
                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                <input type="hidden" name="ouinpo_projects_action" value="add_competency_link">
                <input type="hidden" name="project_id" value="<?php echo esc_attr((string) $projectId); ?>">
                <select name="object_type">
                    <option value="project">Projet</option>
                    <option value="deliverable">Livrable</option>
                </select>
                <select name="object_id">
                    <option value="<?php echo esc_attr((string) $projectId); ?>">Projet entier</option>
                    <?php foreach ($deliverables as $deliverable): ?>
                        <option value="<?php echo esc_attr((string) $deliverable['id']); ?>">
                            <?php echo esc_html('Livrable - ' . (string) $deliverable['title']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="competency_id">
                    <?php foreach ($competencies as $competency): ?>
                        <?php $label = (string) ($competency['label'] ?: $competency['competency']); ?>
                        <option value="<?php echo esc_attr((string) $competency['id']); ?>">
                            <?php echo esc_html((string) ($competency['domain'] ? $competency['domain'] . ' - ' . wp_strip_all_tags($label) : wp_strip_all_tags($label))); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="button" type="submit">Lier</button>
            </form>
        <?php endif; ?>

        <?php if (!$links): ?>
            <p>Aucun lien competence.</p>
        <?php else: ?>
            <table class="widefat striped">
                <thead><tr><th>Objet</th><th>Competence</th><th>Action</th></tr></thead>
                <tbody>
                <?php foreach ($links as $link): ?>
                    <?php $label = (string) ($link['label'] ?: $link['competency'] ?: ('Competence #' . (int) $link['competency_id'])); ?>
                    <tr>
                        <td><?php echo esc_html((string) $link['object_type'] . ' #' . (int) $link['object_id']); ?></td>
                        <td><?php echo esc_html(wp_strip_all_tags($label)); ?></td>
                        <td>
                            <form method="post">
                                <?php wp_nonce_field(self::NONCE_ACTION, self::NONCE_NAME); ?>
                                <input type="hidden" name="ouinpo_projects_action" value="delete_competency_link">
                                <input type="hidden" name="link_id" value="<?php echo esc_attr((string) $link['id']); ?>">
                                <button class="button-link-delete" type="submit">Supprimer</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php
    }

    private static function evidencePanel(Repository $repository, int $projectId): void
    {
        $evidence = array_slice($repository->getEvidence($projectId), 0, 10);
        $frontUrl = add_query_arg(['ouinpo_project_id' => $projectId, 'ouinpo_project_view' => 'sheet'], home_url('/'));
        $btsUrl = add_query_arg(['ouinpo_project_id' => $projectId, 'ouinpo_project_view' => 'bts'], home_url('/'));
        ?>
        <p class="ouinpo-projects-admin-actions">
            <a class="button" href="<?php echo esc_url($frontUrl); ?>">Fiche projet</a>
            <a class="button" href="<?php echo esc_url($btsUrl); ?>">Situation BTS</a>
            <code><?php echo esc_html(rest_url('ouinpo-projects/v1/projects/' . $projectId . '/export/markdown')); ?></code>
        </p>
        <?php if (!$evidence): ?>
            <p>Aucune trace.</p>
        <?php else: ?>
            <table class="widefat striped">
                <thead><tr><th>Trace</th><th>Type</th><th>Fichier/lien</th><th>Date</th></tr></thead>
                <tbody>
                <?php foreach ($evidence as $item): ?>
                    <?php $url = !empty($item['attachment_url']) ? (string) $item['attachment_url'] : (string) ($item['url'] ?? ''); ?>
                    <tr>
                        <td><?php echo esc_html((string) $item['title']); ?></td>
                        <td><?php echo esc_html((string) $item['evidence_type']); ?></td>
                        <td>
                            <?php if ($url !== ''): ?>
                                <a href="<?php echo esc_url($url); ?>"><?php echo esc_html((string) ($item['attachment_filename'] ?: 'ouvrir')); ?></a>
                            <?php elseif (!empty($item['attachment_id'])): ?>
                                <?php echo esc_html('Attachment indisponible #' . (int) $item['attachment_id']); ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html((string) $item['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <?php
    }

    private static function projectsTable(array $projects): void
    {
        if (!$projects) {
            echo '<p>Aucun projet.</p>';
            return;
        }
        ?>
        <table class="widefat striped">
            <thead>
            <tr>
                <th>Titre</th>
                <th>Statut</th>
                <th>Membres</th>
                <th>Taches</th>
                <th>IA eleve</th>
                <th>Shortcode Kanban</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($projects as $project): ?>
                <tr>
                    <td><strong><?php echo esc_html((string) $project['title']); ?></strong></td>
                    <td><?php echo esc_html((string) $project['status']); ?></td>
                    <td><?php echo esc_html((string) ((int) ($project['members_count'] ?? 0))); ?></td>
                    <td><?php echo esc_html((string) ((int) ($project['tasks_count'] ?? 0))); ?></td>
                    <td><?php echo esc_html(!empty($project['student_ai_enabled']) ? 'Activee' : 'Desactivee'); ?></td>
                    <td><code>[ouinpo_project_kanban id="<?php echo esc_html((string) $project['id']); ?>"]</code></td>
                    <td>
                        <a class="button button-secondary" href="<?php echo esc_url(add_query_arg(['page' => self::PAGE, 'project_id' => (int) $project['id']], admin_url('admin.php'))); ?>">
                            Modifier
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private static function userSelect(string $name, int $selected, string $id): void
    {
        $users = get_users([
            'number' => 300,
            'orderby' => 'display_name',
            'order' => 'ASC',
            'fields' => ['ID', 'display_name', 'user_email'],
        ]);
        ?>
        <select id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr($name); ?>">
            <?php if ($selected <= 0): ?>
                <option value="">Choisir</option>
            <?php endif; ?>
            <?php foreach ($users as $user): ?>
                <option value="<?php echo esc_attr((string) $user->ID); ?>" <?php selected($selected, (int) $user->ID); ?>>
                    <?php echo esc_html($user->display_name . ' (' . $user->user_email . ')'); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php
    }

    private static function postedProjectData(): array
    {
        $fields = ['title', 'slug', 'description', 'level', 'class_slug', 'status', 'teacher_id', 'start_date', 'end_date'];
        $data = [];

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $data[$field] = wp_unslash($_POST[$field]);
            }
        }

        $data['student_ai_enabled'] = isset($_POST['student_ai_enabled']) ? 1 : 0;

        return $data;
    }

    private static function postedDeliverableData(): array
    {
        $fields = ['title', 'description', 'type', 'status', 'due_date', 'position'];
        $data = [];

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $data[$field] = wp_unslash($_POST[$field]);
            }
        }

        return $data;
    }

    private static function canManage(): bool
    {
        return current_user_can(Capabilities::PROJECTS_CREATE)
            || current_user_can(Capabilities::PROJECTS_MANAGE_CLASS)
            || current_user_can(Capabilities::PROJECTS_MANAGE_ALL)
            || current_user_can('manage_options');
    }
}
