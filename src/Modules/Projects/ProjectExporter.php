<?php

namespace Ouinpo\Suite\Modules\Projects;

defined('ABSPATH') || exit;

final class ProjectExporter
{
    public static function data(int $projectId): ?array
    {
        $repository = new Repository();
        $project = $repository->getProjectSummary($projectId);
        if (!$project) {
            return null;
        }

        return [
            'project' => $project,
            'members' => $repository->getMembers($projectId),
            'deliverables' => $repository->getDeliverables($projectId),
            'tasks' => $repository->getMainTasks($projectId, 50),
            'logs' => array_slice($repository->getLogs($projectId), 0, 12),
            'evidence' => $repository->getEvidence($projectId),
            'competencies' => $repository->getCompetencyLinks($projectId),
        ];
    }

    public static function renderProjectSheet(int $projectId): string
    {
        $data = self::data($projectId);
        if (!$data) {
            return '<div class="ouinpo-projects-notice">' . esc_html__('Projet introuvable.', 'ouinpo-suite') . '</div>';
        }

        $project = $data['project'];

        ob_start();
        ?>
        <section class="ouinpo-projects-sheet" data-ouinpo-projects-export data-project-id="<?php echo esc_attr((string) $projectId); ?>" data-export-kind="project">
            <?php echo self::toolbar('project'); ?>
            <header class="ouinpo-projects-sheet-header">
                <p>SPOPI Projects - Bureau des Pataprojets Applicatifs</p>
                <h2><?php echo esc_html((string) $project['title']); ?></h2>
                <p><?php echo esc_html(self::period($project)); ?> - <?php echo esc_html((string) $project['status']); ?></p>
            </header>

            <div class="ouinpo-projects-sheet-grid">
                <?php echo self::card('Identite du projet', self::projectIdentity($data)); ?>
                <?php echo self::card('Contexte', self::context($data)); ?>
                <?php echo self::card('Organisation du travail', self::workOrganization($data)); ?>
                <?php echo self::card('Ce que ce projet permet de montrer', self::demonstrationList($data)); ?>
            </div>

            <section class="ouinpo-projects-sheet-section"><h3>Livrables</h3><?php echo self::deliverablesTable($data); ?></section>
            <section class="ouinpo-projects-sheet-section"><h3>Competences</h3><?php echo self::competenciesList($data['competencies']); ?></section>
            <section class="ouinpo-projects-sheet-section"><h3>Traces disponibles</h3><?php echo self::evidenceList($data['evidence']); ?></section>
            <section class="ouinpo-projects-sheet-section"><h3>A completer par l'etudiant</h3><?php echo self::studentFields(); ?></section>
            <textarea class="ouinpo-projects-export-output" data-ouinpo-projects-export-output readonly hidden></textarea>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    public static function renderBtsSituation(int $projectId): string
    {
        $data = self::data($projectId);
        if (!$data) {
            return '<div class="ouinpo-projects-notice">' . esc_html__('Projet introuvable.', 'ouinpo-suite') . '</div>';
        }

        $project = $data['project'];

        ob_start();
        ?>
        <section class="ouinpo-projects-sheet ouinpo-projects-bts-situation" data-ouinpo-projects-export data-project-id="<?php echo esc_attr((string) $projectId); ?>" data-export-kind="bts-situation">
            <?php echo self::toolbar('bts-situation'); ?>
            <header class="ouinpo-projects-sheet-header">
                <p>Situation professionnelle BTS SIO</p>
                <h2><?php echo esc_html((string) $project['title']); ?></h2>
                <p><?php echo esc_html(self::period($project)); ?></p>
            </header>

            <div class="ouinpo-projects-sheet-grid">
                <?php echo self::card('Intitule de la situation', '<p>' . esc_html((string) $project['title']) . '</p>'); ?>
                <?php echo self::card('Cadre', '<p>Formation / atelier / projet pedagogique BTS SIO.</p>'); ?>
                <?php echo self::card('Contexte professionnel', self::context($data)); ?>
                <?php echo self::card('Modalites de travail', self::membersMode($data['members'])); ?>
            </div>

            <section class="ouinpo-projects-sheet-section"><h3>Besoin exprime</h3><?php echo wp_kses_post(wpautop((string) ($project['description'] ?: 'Besoin a preciser.'))); ?></section>
            <section class="ouinpo-projects-sheet-section"><h3>Productions realisees</h3><?php echo self::deliverablesTable($data); ?></section>
            <section class="ouinpo-projects-sheet-section"><h3>Ressources utilisees</h3><?php echo self::evidenceList($data['evidence']); ?></section>
            <section class="ouinpo-projects-sheet-section"><h3>Competences mobilisees</h3><?php echo self::competenciesList($data['competencies']); ?></section>
            <section class="ouinpo-projects-sheet-section"><h3>Bilan et limites</h3><?php echo self::studentFields(); ?></section>
            <textarea class="ouinpo-projects-export-output" data-ouinpo-projects-export-output readonly hidden></textarea>
        </section>
        <?php

        return (string) ob_get_clean();
    }

    public static function projectHtml(int $projectId): string
    {
        return self::renderProjectSheet($projectId);
    }

    public static function projectMarkdown(int $projectId): string
    {
        $data = self::data($projectId);
        if (!$data) {
            return '';
        }

        $project = $data['project'];
        $lines = [
            '# ' . self::md((string) $project['title']),
            '',
            '## Identite du projet',
            '- Periode : ' . self::md(self::period($project)),
            '- Statut : ' . self::md((string) $project['status']),
            '- Professeur referent : #' . (int) $project['teacher_id'],
            '- Membres : ' . self::md(self::memberNames($data['members'])),
            '',
            '## Contexte',
            self::md((string) ($project['description'] ?: 'A completer.')),
            '',
            '## Organisation du travail',
        ];

        foreach ($data['tasks'] as $task) {
            $lines[] = '- ' . self::md((string) $task['title']) . ' (' . self::md((string) $task['status']) . ')';
        }

        $lines[] = '';
        $lines[] = '## Livrables';
        foreach ($data['deliverables'] as $deliverable) {
            $lines[] = '- ' . self::md((string) $deliverable['title']) . ' - ' . self::md((string) $deliverable['status']);
        }

        $lines[] = '';
        $lines[] = '## Competences';
        foreach (self::competencyLabels($data['competencies']) as $label) {
            $lines[] = '- ' . self::md($label);
        }

        $lines[] = '';
        $lines[] = '## Traces disponibles';
        foreach ($data['evidence'] as $item) {
            $line = '- ' . self::md((string) $item['title']) . ' [' . self::md((string) $item['evidence_type']) . ']';
            $url = self::evidenceUrl($item);
            if ($url !== '') {
                $line .= ' - ' . esc_url_raw($url);
            }
            $lines[] = $line;
        }

        $lines[] = '';
        $lines[] = '## Ce que ce projet permet de montrer';
        foreach (self::demonstrationItems($data) as $item) {
            $lines[] = '- ' . self::md($item);
        }

        $lines[] = '';
        $lines[] = '## A completer par l etudiant';
        $lines[] = '- Mon role :';
        $lines[] = '- Difficultes rencontrees :';
        $lines[] = '- Solutions mises en oeuvre :';
        $lines[] = '- Bilan personnel :';

        return implode("\n", $lines) . "\n";
    }

    public static function btsSituationMarkdown(int $projectId): string
    {
        $data = self::data($projectId);
        if (!$data) {
            return '';
        }

        $project = $data['project'];
        $lines = [
            '# Situation professionnelle BTS SIO - ' . self::md((string) $project['title']),
            '',
            '- Periode : ' . self::md(self::period($project)),
            '- Cadre : formation / atelier / projet pedagogique BTS SIO',
            '- Modalites de travail : ' . self::md(count($data['members']) > 1 ? 'Equipe' : 'Individuel'),
            '',
            '## Contexte professionnel',
            self::md((string) ($project['description'] ?: 'A preciser.')),
            '',
            '## Besoin exprime',
            self::md((string) ($project['description'] ?: 'A preciser.')),
            '',
            '## Productions realisees',
        ];

        foreach ($data['deliverables'] as $deliverable) {
            $lines[] = '- ' . self::md((string) $deliverable['title']) . ' - ' . self::md((string) $deliverable['status']);
        }

        $lines[] = '';
        $lines[] = '## Ressources utilisees';
        foreach ($data['evidence'] as $item) {
            $lines[] = '- ' . self::md((string) $item['title']);
        }

        $lines[] = '';
        $lines[] = '## Competences mobilisees';
        foreach (self::competencyLabels($data['competencies']) as $label) {
            $lines[] = '- ' . self::md($label);
        }

        $lines[] = '';
        $lines[] = '## Traces disponibles';
        foreach ($data['evidence'] as $item) {
            $url = self::evidenceUrl($item);
            $lines[] = '- ' . self::md((string) $item['title']) . ($url !== '' ? ' - ' . esc_url_raw($url) : '');
        }

        $lines[] = '';
        $lines[] = '## Bilan et limites';
        $lines[] = 'A completer par l etudiant.';

        return implode("\n", $lines) . "\n";
    }

    private static function toolbar(string $kind): string
    {
        $label = $kind === 'bts-situation' ? 'Copier Markdown BTS' : 'Copier Markdown';

        return '<div class="ouinpo-projects-sheet-toolbar">'
            . '<button type="button" class="ouinpo-projects-button" data-ouinpo-projects-copy-markdown>' . esc_html($label) . '</button>'
            . '<button type="button" class="ouinpo-projects-button ouinpo-projects-button-secondary" data-ouinpo-projects-print>Imprimer / Enregistrer en PDF</button>'
            . '</div>';
    }

    private static function card(string $title, string $content): string
    {
        return '<section><h3>' . esc_html($title) . '</h3>' . $content . '</section>';
    }

    private static function projectIdentity(array $data): string
    {
        $project = $data['project'];

        return '<p><strong>Periode :</strong> ' . esc_html(self::period($project)) . '</p>'
            . '<p><strong>Statut :</strong> ' . esc_html((string) $project['status']) . '</p>'
            . '<p><strong>Niveau :</strong> ' . esc_html((string) ($project['level'] ?: '-')) . '</p>'
            . '<p><strong>Classe :</strong> ' . esc_html((string) ($project['class_slug'] ?: '-')) . '</p>'
            . '<p><strong>Membres :</strong> ' . esc_html(self::memberNames($data['members'])) . '</p>';
    }

    private static function context(array $data): string
    {
        $project = $data['project'];

        return '<p><strong>Besoin / objectif :</strong></p>'
            . wp_kses_post(wpautop((string) ($project['description'] ?: 'A completer.')))
            . '<p><strong>Environnement technique :</strong> a preciser par l etudiant.</p>'
            . '<p><strong>Contraintes :</strong> a preciser par l etudiant.</p>';
    }

    private static function workOrganization(array $data): string
    {
        $html = '<p><strong>Taches principales :</strong></p>' . self::tasksList($data['tasks']);
        $html .= '<p><strong>Journal de bord synthetique :</strong></p>' . self::logsList($data['logs']);

        return $html;
    }

    private static function deliverablesTable(array $data): string
    {
        $deliverables = $data['deliverables'];
        if (!$deliverables) {
            return '<p class="ouinpo-projects-empty">Aucun livrable.</p>';
        }

        ob_start();
        ?>
        <table class="ouinpo-projects-table">
            <thead><tr><th>Livrable</th><th>Type</th><th>Statut</th><th>Echeance</th><th>Traces</th></tr></thead>
            <tbody>
            <?php foreach ($deliverables as $deliverable): ?>
                <tr>
                    <td><?php echo esc_html((string) $deliverable['title']); ?></td>
                    <td><?php echo esc_html((string) $deliverable['type']); ?></td>
                    <td><?php echo esc_html((string) $deliverable['status']); ?></td>
                    <td><?php echo esc_html((string) ($deliverable['due_date'] ?: '-')); ?></td>
                    <td><?php echo esc_html((string) self::countEvidenceForDeliverable($data['evidence'], (int) $deliverable['id'])); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php

        return (string) ob_get_clean();
    }

    private static function evidenceList(array $evidence): string
    {
        if (!$evidence) {
            return '<p class="ouinpo-projects-empty">Aucune trace.</p>';
        }

        ob_start();
        ?>
        <ul class="ouinpo-projects-simple-list ouinpo-projects-evidence-list">
            <?php foreach ($evidence as $item): ?>
                <?php $url = self::evidenceUrl($item); ?>
                <li>
                    <strong><?php echo esc_html((string) $item['title']); ?></strong>
                    <span><?php echo esc_html((string) $item['evidence_type']); ?></span>
                    <?php if ($url !== ''): ?>
                        <a href="<?php echo esc_url($url); ?>" rel="nofollow noopener">ouvrir</a>
                    <?php elseif (!empty($item['attachment_id'])): ?>
                        <span>fichier indisponible</span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php

        return (string) ob_get_clean();
    }

    private static function competenciesList(array $competencies): string
    {
        $labels = self::competencyLabels($competencies);

        if (!$labels) {
            return '<p class="ouinpo-projects-empty">Aucune competence liee.</p>';
        }

        $html = '<ul class="ouinpo-projects-simple-list">';
        foreach ($labels as $label) {
            $html .= '<li>' . esc_html($label) . '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    private static function tasksList(array $tasks): string
    {
        if (!$tasks) {
            return '<p class="ouinpo-projects-empty">Aucune tache.</p>';
        }

        $html = '<ul class="ouinpo-projects-simple-list">';
        foreach ($tasks as $task) {
            $html .= '<li>' . esc_html((string) $task['title']) . ' - ' . esc_html((string) $task['status']) . '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    private static function logsList(array $logs): string
    {
        if (!$logs) {
            return '<p class="ouinpo-projects-empty">Aucune entree.</p>';
        }

        $html = '<ul class="ouinpo-projects-simple-list">';
        foreach ($logs as $log) {
            $html .= '<li>' . esc_html((string) $log['created_at']) . ' - ' . esc_html(wp_strip_all_tags((string) $log['work_done'])) . '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    private static function demonstrationList(array $data): string
    {
        $items = self::demonstrationItems($data);
        $html = '<ul class="ouinpo-projects-simple-list">';
        foreach ($items as $item) {
            $html .= '<li>' . esc_html($item) . '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    private static function demonstrationItems(array $data): array
    {
        $items = [];
        foreach ($data['deliverables'] as $deliverable) {
            if ((string) $deliverable['status'] === 'validated') {
                $items[] = 'Un livrable valide : ' . (string) $deliverable['title'];
            }
        }
        foreach (self::competencyLabels($data['competencies']) as $label) {
            $items[] = 'Une competence mobilisee : ' . $label;
        }
        foreach ($data['evidence'] as $item) {
            $items[] = 'Une trace exploitable : ' . (string) $item['title'];
        }

        return array_slice(array_values(array_unique($items ?: ['Le contexte, les traces et les livrables restent a completer.'])), 0, 8);
    }

    private static function studentFields(): string
    {
        return '<dl class="ouinpo-projects-student-fields">'
            . '<dt>Mon role</dt><dd>A completer.</dd>'
            . '<dt>Difficultes rencontrees</dt><dd>A completer.</dd>'
            . '<dt>Solutions mises en oeuvre</dt><dd>A completer.</dd>'
            . '<dt>Bilan personnel</dt><dd>A completer.</dd>'
            . '</dl>';
    }

    private static function membersMode(array $members): string
    {
        return '<p>' . esc_html(count($members) > 1 ? 'Travail en equipe' : 'Travail individuel') . '</p>'
            . '<p>' . esc_html(self::memberNames($members)) . '</p>';
    }

    private static function memberNames(array $members): string
    {
        $names = [];
        foreach ($members as $member) {
            $names[] = (string) ($member['display_name'] ?: $member['user_email'] ?: ('Utilisateur #' . (int) $member['user_id']));
        }

        return $names ? implode(', ', $names) : 'Aucun membre renseigne';
    }

    private static function competencyLabels(array $competencies): array
    {
        $labels = [];
        foreach ($competencies as $competency) {
            $label = (string) ($competency['label'] ?: $competency['competency'] ?: ('Competence #' . (int) $competency['competency_id']));
            $domain = (string) ($competency['domain'] ?? '');
            $labels[] = $domain !== '' ? $domain . ' - ' . wp_strip_all_tags($label) : wp_strip_all_tags($label);
        }

        return array_values(array_unique($labels));
    }

    private static function countEvidenceForDeliverable(array $evidence, int $deliverableId): int
    {
        $count = 0;
        foreach ($evidence as $item) {
            if ((int) ($item['deliverable_id'] ?? 0) === $deliverableId) {
                $count++;
            }
        }

        return $count;
    }

    private static function evidenceUrl(array $item): string
    {
        if (!empty($item['attachment_url'])) {
            return (string) $item['attachment_url'];
        }

        return !empty($item['url']) ? (string) $item['url'] : '';
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

    private static function md(string $value): string
    {
        $value = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $value);
        $value = wp_strip_all_tags((string) $value, true);
        $value = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
        $value = str_replace(["\r\n", "\r"], "\n", $value);

        return trim($value);
    }
}
