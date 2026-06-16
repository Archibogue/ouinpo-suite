<?php

namespace Ouinpo\Suite\Core\School;

defined('ABSPATH') || exit;

final class YearClosurePlanner
{
    private CycleRepository $cycles;
    private CycleTransitionResolver $resolver;

    public function __construct(?CycleRepository $cycles = null, ?CycleTransitionResolver $resolver = null)
    {
        $this->cycles = $cycles ?: new CycleRepository();
        $this->resolver = $resolver ?: new CycleTransitionResolver($this->cycles);
    }

    public function plan(int $fromYearId = 0, array $options = [], bool $logDryRun = false): array
    {
        global $wpdb;

        $tables = $this->tables();
        $fromYear = $fromYearId > 0
            ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['years']} WHERE id = %d", $fromYearId), ARRAY_A)
            : $wpdb->get_row("SELECT * FROM {$tables['years']} WHERE is_active = 1 ORDER BY id DESC LIMIT 1", ARRAY_A);

        if (!$fromYear) {
            return ['summary' => ['error' => 'no_active_year'], 'items' => []];
        }

        $next = $this->proposeNextYear($fromYear, $options);
        $toYear = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tables['years']} WHERE slug = %s LIMIT 1", $next['slug']), ARRAY_A);
        $groupStatusWhere = $this->columnExists($tables['groups'], 'status') ? " AND g.status <> 'archived'" : '';
        $groups = $wpdb->get_results($wpdb->prepare(
            "SELECT g.*, l.label AS level_label, l.cycle_id
             FROM {$tables['groups']} g
             LEFT JOIN {$tables['levels']} l ON l.id = g.school_level_id
             WHERE g.year_id = %d{$groupStatusWhere}
             ORDER BY g.label ASC",
            (int) $fromYear['id']
        ), ARRAY_A) ?: [];

        $items = [];
        $classPlans = [];
        $summary = [
            'from_year' => $fromYear,
            'to_year' => $toYear ?: $next,
            'to_year_exists' => (bool) $toYear,
            'classes_to_create' => 0,
            'classes_to_archive' => count($groups),
            'students_promoted' => 0,
            'students_same_cycle' => 0,
            'students_cycle_exit' => 0,
            'students_cycle_change' => 0,
            'students_redoublement' => 0,
            'students_to_alumni' => 0,
            'active_projects_to_carry' => 0,
            'explicit_projects_to_carry' => 0,
            'legacy_member_projects_to_carry' => 0,
            'portfolio_projects_to_preserve' => 0,
            'alumni_archive_projects_to_prepare' => 0,
            'annual_data_to_reset_later' => 0,
            'gdpr_purges_planned' => 0,
        ];

        foreach ($groups as $group) {
            $members = $wpdb->get_results($wpdb->prepare(
                "SELECT gm.user_id, gm.school_level_id_override
                 FROM {$tables['members']} gm
                 WHERE gm.group_id = %d AND gm.role = 'student'",
                (int) $group['id']
            ), ARRAY_A) ?: [];

            $effectiveLevelId = (int) ($group['school_level_id'] ?? 0);
            $transition = $effectiveLevelId > 0 ? $this->resolver->resolveDefaultNextLevel($effectiveLevelId) : $this->resolver->resolve(0, null);
            $targetLevel = !empty($transition['to_level_id']) ? $this->cycles->getLevel((int) $transition['to_level_id']) : null;

            $classPlans[] = [
                'source_group_id' => (int) $group['id'],
                'source_label' => (string) $group['label'],
                'from_level_id' => $effectiveLevelId,
                'from_level_label' => (string) ($group['level_label'] ?? ''),
                'from_cycle_id' => !empty($transition['from_cycle_id']) ? (int) $transition['from_cycle_id'] : null,
                'to_level_id' => $transition['to_level_id'],
                'to_level_label' => (string) ($targetLevel['label'] ?? ''),
                'transition' => $transition,
                'students_count' => count($members),
                'target_label' => $targetLevel ? (string) $targetLevel['label'] . ' - ' . (string) $group['label'] : '',
            ];

            if (!empty($transition['to_level_id'])) {
                $summary['classes_to_create']++;
            }

            foreach ($members as $member) {
                $memberLevelId = !empty($member['school_level_id_override']) ? (int) $member['school_level_id_override'] : $effectiveLevelId;
                $memberTransition = $memberLevelId > 0 ? $this->resolver->resolveDefaultNextLevel($memberLevelId) : $transition;
                $summary['students_promoted']++;
                if (!empty($memberTransition['stays_in_same_cycle'])) {
                    $summary['students_same_cycle']++;
                }
                if (!empty($memberTransition['exits_cycle'])) {
                    $summary['students_cycle_exit']++;
                }
                if (!empty($memberTransition['enters_new_cycle'])) {
                    $summary['students_cycle_change']++;
                }
                if (!empty($memberTransition['is_redoublement'])) {
                    $summary['students_redoublement']++;
                }
                if (empty($memberTransition['to_level_id'])) {
                    $summary['students_to_alumni']++;
                    $summary['gdpr_purges_planned'] += 5;
                }
                $summary['annual_data_to_reset_later']++;
            }
        }

        $summary['class_plans'] = $classPlans;

        $projectCounters = $this->projectCountersForClosureGroups($classPlans, (int) ($fromYear['id'] ?? 0));
        foreach ($projectCounters as $key => $value) {
            $summary[$key] = $value;
        }

        $items[] = ['step' => 'year', 'object_type' => 'academic_year', 'object_id' => (int) ($toYear['id'] ?? 0), 'action' => $toYear ? 'select' : 'create', 'status' => 'planned', 'message' => $toYear ? 'Annee cible existante.' : 'Annee cible a creer.'];
        foreach ($classPlans as $plan) {
            $items[] = ['step' => 'groups', 'object_type' => 'group', 'object_id' => $plan['source_group_id'], 'action' => empty($plan['to_level_id']) ? 'archive_only' : 'create_target', 'status' => 'planned', 'message' => $plan['source_label']];
        }
        if ($summary['students_to_alumni'] > 0) {
            $items[] = ['step' => 'gdpr', 'object_type' => 'learning_data', 'object_id' => null, 'action' => 'purge', 'status' => 'planned', 'message' => 'Non execute dans cette version : purge RGPD a implementer apres validation.'];
        }

        if ($logDryRun) {
            $logger = new YearClosureLogger();
            $runId = $logger->startRun((int) $fromYear['id'], $toYear ? (int) $toYear['id'] : null, 'dry_run', $options, $summary);
            foreach ($items as $item) {
                $logger->addItem($runId, $item['step'], $item['object_type'], $item['object_id'], $item['action'], $item['status'], $item['message']);
            }
            $summary['run_id'] = $runId;
        }

        return ['summary' => $summary, 'items' => $items];
    }

    public function proposeNextYear(array $fromYear, array $options = []): array
    {
        $slug = sanitize_text_field((string) ($options['to_year_slug'] ?? ''));
        if ($slug === '' && preg_match('/^(\d{4})-(\d{4})$/', (string) ($fromYear['slug'] ?? ''), $m)) {
            $slug = ((int) $m[1] + 1) . '-' . ((int) $m[2] + 1);
        }
        if ($slug === '') {
            $slug = gmdate('Y') . '-' . ((int) gmdate('Y') + 1);
        }

        $starts = sanitize_text_field((string) ($options['to_year_starts_on'] ?? ''));
        $ends = sanitize_text_field((string) ($options['to_year_ends_on'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $starts)) {
            $starts = !empty($fromYear['starts_on']) ? gmdate('Y-m-d', strtotime((string) $fromYear['starts_on'] . ' +1 year')) : substr($slug, 0, 4) . '-09-01';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $ends)) {
            $ends = !empty($fromYear['ends_on']) ? gmdate('Y-m-d', strtotime((string) $fromYear['ends_on'] . ' +1 year')) : substr($slug, 5, 4) . '-08-31';
        }

        return ['id' => 0, 'slug' => $slug, 'starts_on' => $starts, 'ends_on' => $ends, 'is_active' => 0, 'status' => 'draft'];
    }

    private function tables(): array
    {
        global $wpdb;

        return [
            'years' => $wpdb->prefix . 'ouin_exo_academic_years',
            'groups' => $wpdb->prefix . 'ouin_exo_groups',
            'members' => $wpdb->prefix . 'ouin_exo_group_members',
            'levels' => $wpdb->prefix . 'ouin_exo_school_levels',
        ];
    }

    private function columnExists(string $table, string $column): bool
    {
        global $wpdb;

        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return false;
        }

        return (bool) $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
    }

    private function projectCountersForClosureGroups(array $classPlans, int $fromYearId): array
    {
        if (!class_exists('\Ouinpo\Suite\Modules\Projects\Repository')) {
            return [
                'active_projects_to_carry' => 0,
                'explicit_projects_to_carry' => 0,
                'legacy_member_projects_to_carry' => 0,
                'portfolio_projects_to_preserve' => 0,
                'alumni_archive_projects_to_prepare' => 0,
            ];
        }

        $repository = new \Ouinpo\Suite\Modules\Projects\Repository();
        $projects = [];
        foreach ($classPlans as $plan) {
            $groupId = (int) ($plan['source_group_id'] ?? 0);
            if ($groupId <= 0) {
                continue;
            }

            foreach ($repository->findProjectsForClosureGroup($groupId, $fromYearId > 0 ? $fromYearId : null) as $project) {
                $projectId = (int) ($project['id'] ?? 0);
                if ($projectId > 0 && !isset($projects[$projectId])) {
                    $projects[$projectId] = $project;
                }
            }
        }

        $counters = [
            'active_projects_to_carry' => count($projects),
            'explicit_projects_to_carry' => 0,
            'legacy_member_projects_to_carry' => 0,
            'portfolio_projects_to_preserve' => 0,
            'alumni_archive_projects_to_prepare' => 0,
        ];

        foreach ($projects as $project) {
            $source = (string) ($project['closure_detection_source'] ?? '');
            if ($source === 'project_members') {
                $counters['legacy_member_projects_to_carry']++;
            } else {
                $counters['explicit_projects_to_carry']++;
            }

            if (!empty($project['is_portfolio_relevant']) || (string) ($project['closure_policy'] ?? '') === 'never_purge_automatically') {
                $counters['portfolio_projects_to_preserve']++;
                $counters['alumni_archive_projects_to_prepare']++;
            }
        }

        return $counters;
    }
}
