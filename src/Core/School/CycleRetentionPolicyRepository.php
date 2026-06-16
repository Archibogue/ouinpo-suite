<?php

namespace Ouinpo\Suite\Core\School;

defined('ABSPATH') || exit;

final class CycleRetentionPolicyRepository
{
    public const DEFAULT_DOMAINS_WITH_PORTFOLIO = [
        ['projects', 'cycle', 'keep', 'archive_readonly', 'view_export'],
        ['project_tasks', 'cycle', 'keep', 'archive_readonly', 'view_export'],
        ['project_journal', 'cycle', 'keep', 'archive_readonly', 'view_export'],
        ['project_deliverables', 'cycle', 'keep', 'archive_readonly', 'view_export'],
        ['project_evidence', 'cycle', 'keep', 'archive_readonly', 'view_export'],
        ['project_competency_links', 'cycle', 'keep', 'archive', 'view_export'],
        ['portfolio', 'cycle', 'keep', 'lock', 'view_export'],
        ['exercise_status', 'year', 'reset', 'purge', 'none'],
        ['exercise_reveals', 'year', 'reset', 'purge', 'none'],
        ['exercise_answers', 'year', 'reset', 'purge', 'none'],
        ['competencies_annual', 'year', 'reset', 'anonymize', 'none'],
        ['badges_annual', 'year', 'reset', 'purge', 'none'],
        ['ai_raw', 'year', 'purge', 'purge', 'none'],
        ['ocr_raw', 'year', 'purge', 'purge', 'none'],
        ['flashcards', 'year', 'reset', 'purge', 'none'],
        ['submissions', 'year', 'archive', 'archive_readonly', 'none'],
    ];

    public const DEFAULT_DOMAINS_NO_PORTFOLIO = [
        ['projects', 'cycle', 'archive_readonly', 'archive_readonly', 'none'],
        ['project_deliverables', 'cycle', 'archive_readonly', 'archive_readonly', 'none'],
        ['project_evidence', 'cycle', 'archive_readonly', 'archive_readonly', 'none'],
        ['exercise_status', 'year', 'reset', 'purge', 'none'],
        ['competencies_annual', 'year', 'reset', 'purge', 'none'],
        ['flashcards', 'year', 'reset', 'purge', 'none'],
        ['submissions', 'year', 'archive', 'archive_readonly', 'none'],
    ];

    public function table(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'ouinpo_cycle_data_policies';
    }

    public function listForCycle(int $cycleId): array
    {
        global $wpdb;

        if ($cycleId <= 0) {
            return [];
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table()} WHERE cycle_id = %d ORDER BY data_domain ASC",
            $cycleId
        ), ARRAY_A) ?: [];
    }

    public function ensureDefaults(int $cycleId, bool $portfolioEnabled): int
    {
        global $wpdb;

        if ($cycleId <= 0) {
            return 0;
        }

        $rows = $portfolioEnabled ? self::DEFAULT_DOMAINS_WITH_PORTFOLIO : self::DEFAULT_DOMAINS_NO_PORTFOLIO;
        $created = 0;

        foreach ($rows as $row) {
            [$domain, $scope, $sameCycle, $cycleExit, $alumniAccess] = $row;
            $exists = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table()} WHERE cycle_id = %d AND data_domain = %s",
                $cycleId,
                $domain
            ));
            if ($exists > 0) {
                continue;
            }

            $ok = $wpdb->insert(
                $this->table(),
                [
                    'cycle_id' => $cycleId,
                    'data_domain' => $domain,
                    'scope' => $scope,
                    'action_same_cycle' => $sameCycle,
                    'action_cycle_exit' => $cycleExit,
                    'alumni_access' => $alumniAccess,
                    'retention_months_after_exit' => null,
                ],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%d']
            );

            if ($ok) {
                $created++;
            }
        }

        return $created;
    }
}
