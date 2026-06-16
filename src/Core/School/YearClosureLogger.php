<?php

namespace Ouinpo\Suite\Core\School;

defined('ABSPATH') || exit;

final class YearClosureLogger
{
    public function runsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'ouinpo_year_closure_runs';
    }

    public function itemsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'ouinpo_year_closure_items';
    }

    public function startRun(int $fromYearId, ?int $toYearId, string $mode, array $options = [], array $summary = []): int
    {
        global $wpdb;

        $ok = $wpdb->insert(
            $this->runsTable(),
            [
                'from_year_id' => $fromYearId,
                'to_year_id' => $toYearId,
                'status' => $mode === 'dry_run' ? 'draft' : 'running',
                'mode' => $mode,
                'options_json' => wp_json_encode($options),
                'summary_json' => wp_json_encode($summary),
                'started_by' => get_current_user_id(),
                'started_at' => current_time('mysql'),
                'finished_at' => null,
                'error_message' => null,
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
        );

        return $ok ? (int) $wpdb->insert_id : 0;
    }

    public function finishRun(int $runId, string $status, array $summary = [], string $error = ''): void
    {
        global $wpdb;

        if ($runId <= 0) {
            return;
        }

        $wpdb->update(
            $this->runsTable(),
            [
                'status' => $status,
                'summary_json' => wp_json_encode($summary),
                'finished_at' => current_time('mysql'),
                'error_message' => $error !== '' ? $error : null,
            ],
            ['id' => $runId],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );
    }

    public function addItem(int $runId, string $step, string $objectType, ?int $objectId, string $action, string $status, string $message = '', ?int $before = null, ?int $after = null): void
    {
        global $wpdb;

        if ($runId <= 0) {
            return;
        }

        $wpdb->insert(
            $this->itemsTable(),
            [
                'run_id' => $runId,
                'step' => substr(sanitize_key($step), 0, 80),
                'object_type' => substr(sanitize_key($objectType), 0, 80),
                'object_id' => $objectId,
                'action' => substr(sanitize_key($action), 0, 80),
                'status' => substr(sanitize_key($status), 0, 30),
                'message' => sanitize_text_field($message),
                'count_before' => $before,
                'count_after' => $after,
                'created_at' => current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s']
        );
    }
}
