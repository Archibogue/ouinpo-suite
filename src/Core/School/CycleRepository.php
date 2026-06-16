<?php

namespace Ouinpo\Suite\Core\School;

defined('ABSPATH') || exit;

final class CycleRepository
{
    public function cyclesTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'ouinpo_cycles';
    }

    public function levelsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'ouin_exo_school_levels';
    }

    public function transitionsTable(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'ouinpo_level_transitions';
    }

    public function listCycles(bool $includeArchived = true): array
    {
        global $wpdb;

        $where = $includeArchived ? '1 = 1' : "status = 'active'";

        return $wpdb->get_results(
            "SELECT * FROM {$this->cyclesTable()} WHERE {$where} ORDER BY status ASC, label ASC, id ASC",
            ARRAY_A
        ) ?: [];
    }

    public function getCycle(int $cycleId): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->cyclesTable()} WHERE id = %d LIMIT 1",
            $cycleId
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function getLevel(int $levelId): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->levelsTable()} WHERE id = %d LIMIT 1",
            $levelId
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function listLevels(): array
    {
        global $wpdb;

        $hasSort = (bool) $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$this->levelsTable()} LIKE %s", 'sort_order'));
        $order = $hasSort ? 'sort_order ASC, id ASC' : 'id ASC';

        return $wpdb->get_results("SELECT * FROM {$this->levelsTable()} ORDER BY {$order}", ARRAY_A) ?: [];
    }

    public function listLevelsForCycle(int $cycleId): array
    {
        global $wpdb;

        if ($cycleId <= 0) {
            return [];
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->levelsTable()} WHERE cycle_id = %d ORDER BY cycle_rank ASC, id ASC",
            $cycleId
        ), ARRAY_A) ?: [];
    }

    public function saveCycle(array $data, int $cycleId = 0): int
    {
        global $wpdb;

        $now = current_time('mysql');
        $label = substr(sanitize_text_field((string) ($data['label'] ?? '')), 0, 190);
        $slug = sanitize_title((string) ($data['slug'] ?? $label));
        $slug = substr($slug, 0, 120);

        if ($label === '' || $slug === '') {
            return 0;
        }

        $payload = [
            'slug' => $slug,
            'label' => $label,
            'description' => wp_kses_post((string) ($data['description'] ?? '')),
            'duration_years' => !empty($data['duration_years']) ? max(1, min(20, (int) $data['duration_years'])) : null,
            'status' => in_array(($data['status'] ?? 'active'), ['active', 'inactive', 'archived'], true) ? (string) $data['status'] : 'active',
            'portfolio_enabled' => !empty($data['portfolio_enabled']) ? 1 : 0,
            'default_policy_json' => isset($data['default_policy_json']) ? wp_json_encode((array) $data['default_policy_json']) : null,
            'updated_at' => $now,
        ];

        $formats = ['%s', '%s', '%s', '%d', '%s', '%d', '%s', '%s'];

        if ($cycleId > 0) {
            $ok = $wpdb->update($this->cyclesTable(), $payload, ['id' => $cycleId], $formats, ['%d']);

            return $ok === false ? 0 : $cycleId;
        }

        $payload['created_at'] = $now;
        $ok = $wpdb->insert($this->cyclesTable(), $payload, array_merge($formats, ['%s']));

        return $ok ? (int) $wpdb->insert_id : 0;
    }

    public function setCycleStatus(int $cycleId, string $status): bool
    {
        global $wpdb;

        if (!in_array($status, ['active', 'inactive', 'archived'], true)) {
            return false;
        }

        return false !== $wpdb->update(
            $this->cyclesTable(),
            ['status' => $status, 'updated_at' => current_time('mysql')],
            ['id' => $cycleId],
            ['%s', '%s'],
            ['%d']
        );
    }

    public function defaultTransitionForLevel(int $fromLevelId): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->transitionsTable()}
             WHERE from_level_id = %d AND is_default = 1
             ORDER BY id ASC LIMIT 1",
            $fromLevelId
        ), ARRAY_A);

        return is_array($row) ? $row : null;
    }
}
