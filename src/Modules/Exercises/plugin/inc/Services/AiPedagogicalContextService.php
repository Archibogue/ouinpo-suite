<?php

namespace Ouinpo\Exercises\Services;

defined('ABSPATH') || exit;

final class AiPedagogicalContextService
{
    public static function forExercise(int $exercise_id, array $args = []): array
    {
        $user_id = (int) ($args['user_id'] ?? (is_user_logged_in() ? get_current_user_id() : 0));
        $group_id = (int) ($args['group_id'] ?? 0);
        $competency_ids = self::competencyIdsForExercise($exercise_id);

        return self::build([
            'kind' => 'exercise',
            'exercise_id' => $exercise_id,
            'user_id' => $user_id,
            'group_id' => $group_id,
            'exercise_levels' => self::levelsForExercise($exercise_id),
            'competency_levels' => self::levelsForCompetencies($competency_ids),
            'competency_ids' => $competency_ids,
        ]);
    }

    public static function forCorrectionItem(array $item, array $args = []): array
    {
        $exercise_id = (int) ($item['exercise_id'] ?? $item['id'] ?? 0);
        $competency_ids = [];
        foreach ((array) ($item['competencies'] ?? []) as $competency) {
            if (is_array($competency)) {
                $id = (int) ($competency['id'] ?? 0);
                if ($id > 0) {
                    $competency_ids[$id] = $id;
                }
            }
        }

        if (!$competency_ids && $exercise_id > 0) {
            $competency_ids = self::competencyIdsForExercise($exercise_id);
        }

        return self::build([
            'kind' => 'correction_item',
            'exercise_id' => $exercise_id,
            'user_id' => (int) ($args['user_id'] ?? 0),
            'group_id' => (int) ($args['group_id'] ?? 0),
            'exercise_levels' => $exercise_id > 0 ? self::levelsForExercise($exercise_id) : [],
            'competency_levels' => self::levelsForCompetencies(array_values($competency_ids)),
            'competency_ids' => array_values($competency_ids),
        ]);
    }

    public static function forWrittenQuestion(array $subject, array $exercise, array $question, array $args = []): array
    {
        $competency_ids = self::idsFromCompetencyRows((array) ($question['competencies'] ?? []));
        $subject_id = (int) ($subject['id'] ?? $question['subject_id'] ?? 0);

        return self::build([
            'kind' => 'written_question',
            'subject_id' => $subject_id,
            'question_id' => (int) ($question['id'] ?? 0),
            'user_id' => (int) ($args['user_id'] ?? (is_user_logged_in() ? get_current_user_id() : 0)),
            'group_id' => (int) ($args['group_id'] ?? 0),
            'subject_levels' => self::levelsForWrittenSubject($subject_id),
            'competency_levels' => self::levelsForCompetencies($competency_ids),
            'competency_ids' => $competency_ids,
        ]);
    }

    public static function forWrittenReport(array $subject, array $args = []): array
    {
        $competency_ids = [];
        foreach ((array) ($subject['exercises'] ?? []) as $exercise) {
            foreach ((array) ($exercise['questions'] ?? []) as $question) {
                foreach (self::idsFromCompetencyRows((array) ($question['competencies'] ?? [])) as $id) {
                    $competency_ids[$id] = $id;
                }
            }
        }

        $subject_id = (int) ($subject['id'] ?? 0);

        return self::build([
            'kind' => 'written_report',
            'subject_id' => $subject_id,
            'user_id' => (int) ($args['user_id'] ?? (is_user_logged_in() ? get_current_user_id() : 0)),
            'group_id' => (int) ($args['group_id'] ?? 0),
            'subject_levels' => self::levelsForWrittenSubject($subject_id),
            'competency_levels' => self::levelsForCompetencies(array_values($competency_ids)),
            'competency_ids' => array_values($competency_ids),
        ]);
    }

    public static function studentLevelForUser(int $user_id): ?array
    {
        return self::levelForUser($user_id);
    }

    public static function promptPayload(array $context): array
    {
        return [
            'reference_level' => self::publicLevel($context['reference_level'] ?? null),
            'expected_level' => self::publicLevel($context['expected_level'] ?? null),
            'expected_level_source' => (string) ($context['expected_level_source'] ?? ''),
            'expected_levels' => array_map([self::class, 'publicLevel'], (array) ($context['expected_levels'] ?? [])),
            'competencies' => array_values((array) ($context['competencies'] ?? [])),
            'relation' => $context['relation'] ?? ['kind' => 'unknown'],
            'cumulative_levels_enabled' => !empty($context['cumulative_levels_enabled']),
            'rules' => self::rulesForPrompt($context),
        ];
    }

    public static function promptBlock(array $context): string
    {
        $payload = self::promptPayload($context);
        $json = wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($json) ? $json : '{}';
    }

    public static function programGuardrail(array $context): string
    {
        $relation = (array) ($context['relation'] ?? []);
        $kind = (string) ($relation['kind'] ?? '');

        if ($kind === 'expected_above_reference') {
            return 'Attention programme : la question releve d un niveau plus avance dans le meme cycle que le niveau de reference. Corriger selon le niveau attendu, expliquer avec prudence et ne pas penaliser le decalage de programme.';
        }

        if ($kind === 'different_cycle') {
            return 'Prudence programme : le niveau attendu et le niveau de reference appartiennent a des cycles differents. Ne pas comparer leurs ordres automatiquement.';
        }

        if ($kind === 'unknown_order') {
            return 'Prudence programme : les niveaux sont configurables mais leur ordre dans le cycle n est pas assez renseigne pour conclure.';
        }

        return '';
    }

    public static function courseContext(string $query, array $context, int $limit = 4, int $max_tokens = 1200, ?array &$debug = null): string
    {
        $debug = [
            'count' => 0,
            'sources' => '',
        ];

        $query = self::enrichRagQuery($query, $context);
        if ($query === '' || !class_exists('\OuInPo\SegFault\RAG')) {
            return '';
        }

        $chunks = [];

        try {
            if (method_exists('\OuInPo\SegFault\RAG', 'search_courses_by_competency')) {
                $chunks = array_merge($chunks, \OuInPo\SegFault\RAG::search_courses_by_competency($query, min(3, $limit), -1));
            }

            if (count($chunks) < $limit && method_exists('\OuInPo\SegFault\RAG', 'search')) {
                $chunks = array_merge($chunks, \OuInPo\SegFault\RAG::search($query, $limit, -1));
            }
        } catch (\Throwable $e) {
            \Ouinpo\Suite\Core\AiSettings::debug_log('Pedagogical RAG context unavailable', [
                'stage' => 'ai_pedagogical_context',
                'error' => $e->getMessage(),
            ]);
            return '';
        }

        $filtered = self::filterCourseChunks($chunks, $limit);
        if (!$filtered || !method_exists('\OuInPo\SegFault\RAG', 'format_context')) {
            return '';
        }

        $debug['count'] = count($filtered);
        $debug['sources'] = implode(' | ', array_map(static function (array $chunk): string {
            $title = trim((string) ($chunk['title'] ?? 'Document'));
            $url = trim((string) ($chunk['url'] ?? ''));
            return $url !== '' ? $title . ' <' . $url . '>' : $title;
        }, $filtered));

        return \OuInPo\SegFault\RAG::format_context($filtered, max(400, $max_tokens));
    }

    public static function debugMeta(array $context): array
    {
        $relation = (array) ($context['relation'] ?? []);
        $expected = self::publicLevel($context['expected_level'] ?? null);
        $reference = self::publicLevel($context['reference_level'] ?? null);

        return [
            'expected_level_id' => (int) ($expected['id'] ?? 0),
            'expected_level_label' => (string) ($expected['label'] ?? ''),
            'expected_cycle_id' => (int) ($expected['cycle_id'] ?? 0),
            'expected_order_in_cycle' => (int) ($expected['order_in_cycle'] ?? 0),
            'reference_level_id' => (int) ($reference['id'] ?? 0),
            'reference_level_label' => (string) ($reference['label'] ?? ''),
            'relation_kind' => (string) ($relation['kind'] ?? ''),
        ];
    }

    private static function build(array $data): array
    {
        $user_id = (int) ($data['user_id'] ?? 0);
        $group_id = (int) ($data['group_id'] ?? 0);
        $student_level = $user_id > 0 ? self::levelForUser($user_id) : null;
        $class_level = !$student_level && $group_id > 0 ? self::levelForGroup($group_id) : null;
        $reference_level = $student_level ?: $class_level;

        $competency_levels = self::uniqueLevels((array) ($data['competency_levels'] ?? []));
        $exercise_levels = self::uniqueLevels((array) ($data['exercise_levels'] ?? []));
        $subject_levels = self::uniqueLevels((array) ($data['subject_levels'] ?? []));

        $expected_levels = $competency_levels ?: ($exercise_levels ?: $subject_levels);
        $expected_source = $competency_levels ? 'competencies' : ($exercise_levels ? 'exercise' : ($subject_levels ? 'subject' : ''));
        $expected_level = self::primaryExpectedLevel($expected_levels, $reference_level);
        $relation = self::compareLevels($expected_level, $reference_level);
        $competency_ids = array_values(array_unique(array_map('intval', (array) ($data['competency_ids'] ?? []))));

        return [
            'kind' => (string) ($data['kind'] ?? ''),
            'user_id' => $user_id,
            'group_id' => $group_id,
            'student_level' => $student_level,
            'class_level' => $class_level,
            'reference_level' => $reference_level,
            'expected_level' => $expected_level,
            'expected_levels' => array_values($expected_levels),
            'expected_level_source' => $expected_source,
            'exercise_levels' => array_values($exercise_levels),
            'subject_levels' => array_values($subject_levels),
            'competency_levels' => array_values($competency_levels),
            'competency_ids' => $competency_ids,
            'competencies' => self::competenciesByIds($competency_ids),
            'relation' => $relation,
            'cumulative_levels_enabled' => self::cumulativeLevelsEnabled(),
        ];
    }

    private static function rulesForPrompt(array $context): array
    {
        return [
            'Corriger selon le niveau attendu de la question ou de l exercice, pas selon un slug historique.',
            'Utiliser le cycle et l ordre du niveau dans le cycle pour comparer deux niveaux configurables.',
            'Si le niveau attendu est plus avance que le niveau de reference dans le meme cycle, garder les attendus de la question mais formuler le retour avec prudence.',
            'Si les cycles different, ne pas deduire qu un niveau est plus facile ou plus avance uniquement par son libelle.',
            'Le niveau de reference sert a adapter le feedback, pas a abaisser les attendus explicites de l enonce.',
        ];
    }

    private static function enrichRagQuery(string $query, array $context): string
    {
        $parts = [trim(wp_strip_all_tags($query))];
        $expected = (array) ($context['expected_level'] ?? []);
        $reference = (array) ($context['reference_level'] ?? []);

        if (!empty($expected['label'])) {
            $parts[] = 'Programme niveau attendu: ' . (string) $expected['label'];
        }

        if (!empty($expected['cycle_label'])) {
            $parts[] = 'Cycle attendu: ' . (string) $expected['cycle_label'];
        }

        if (!empty($reference['label'])) {
            $parts[] = 'Niveau de reference eleve ou classe: ' . (string) $reference['label'];
        }

        foreach (array_slice((array) ($context['competencies'] ?? []), 0, 8) as $competency) {
            if (!is_array($competency)) {
                continue;
            }

            $parts[] = trim(implode(' ', array_values(array_filter([
                (string) ($competency['track'] ?? ''),
                (string) ($competency['domain'] ?? ''),
                (string) ($competency['label'] ?? ''),
            ]))));
        }

        return trim(implode("\n", array_values(array_filter($parts))));
    }

    private static function filterCourseChunks(array $chunks, int $limit): array
    {
        $filtered = [];
        $seen = [];

        foreach ($chunks as $chunk) {
            if (!is_array($chunk)) {
                continue;
            }

            $ptype = strtolower(trim((string) ($chunk['ptype'] ?? ($chunk['type'] ?? ($chunk['origin'] ?? '')))));
            $origin = strtolower(trim((string) ($chunk['origin'] ?? '')));
            if ($ptype === 'exercise' || $origin === 'exercise') {
                continue;
            }

            $key = (string) ($chunk['url'] ?? '');
            if ($key === '') {
                $key = (string) ($chunk['title'] ?? '') . '|' . substr((string) ($chunk['chunk'] ?? ''), 0, 80);
            }

            if ($key !== '' && isset($seen[$key])) {
                continue;
            }

            if ($key !== '') {
                $seen[$key] = true;
            }

            $filtered[] = $chunk;
            if (count($filtered) >= $limit) {
                break;
            }
        }

        return $filtered;
    }

    private static function compareLevels(?array $expected, ?array $reference): array
    {
        if (!$expected) {
            return ['kind' => 'no_expected_level'];
        }

        if (!$reference) {
            return ['kind' => 'no_reference_level'];
        }

        $expected_cycle = (int) ($expected['cycle_id'] ?? 0);
        $reference_cycle = (int) ($reference['cycle_id'] ?? 0);
        $expected_order = (int) ($expected['order_in_cycle'] ?? 0);
        $reference_order = (int) ($reference['order_in_cycle'] ?? 0);

        if ($expected_cycle > 0 && $reference_cycle > 0 && $expected_cycle !== $reference_cycle) {
            return [
                'kind' => 'different_cycle',
                'same_cycle' => false,
                'is_above_reference_level_in_cycle' => false,
            ];
        }

        if ($expected_cycle <= 0 || $reference_cycle <= 0 || $expected_order <= 0 || $reference_order <= 0) {
            return [
                'kind' => 'unknown_order',
                'same_cycle' => $expected_cycle > 0 && $expected_cycle === $reference_cycle,
                'is_above_reference_level_in_cycle' => false,
            ];
        }

        if ($expected_order > $reference_order) {
            return [
                'kind' => 'expected_above_reference',
                'same_cycle' => true,
                'is_above_reference_level_in_cycle' => true,
            ];
        }

        if ($expected_order < $reference_order) {
            return [
                'kind' => 'expected_below_reference',
                'same_cycle' => true,
                'is_above_reference_level_in_cycle' => false,
            ];
        }

        return [
            'kind' => 'same_level',
            'same_cycle' => true,
            'is_above_reference_level_in_cycle' => false,
        ];
    }

    private static function primaryExpectedLevel(array $levels, ?array $reference): ?array
    {
        $levels = self::uniqueLevels($levels);
        if (!$levels) {
            return null;
        }

        $reference_cycle = (int) ($reference['cycle_id'] ?? 0);
        if ($reference_cycle > 0) {
            $same_cycle = array_values(array_filter($levels, static fn(array $level): bool => (int) ($level['cycle_id'] ?? 0) === $reference_cycle));
            if ($same_cycle) {
                return self::highestLevel($same_cycle);
            }
        }

        $cycles = [];
        foreach ($levels as $level) {
            $cycles[(int) ($level['cycle_id'] ?? 0)] = true;
        }

        if (count($cycles) === 1) {
            return self::highestLevel($levels);
        }

        return self::highestLevel($levels);
    }

    private static function highestLevel(array $levels): ?array
    {
        if (!$levels) {
            return null;
        }

        usort($levels, static function (array $a, array $b): int {
            $ao = (int) ($a['order_in_cycle'] ?? 0);
            $bo = (int) ($b['order_in_cycle'] ?? 0);
            if ($ao !== $bo) {
                return $bo <=> $ao;
            }

            $as = (int) ($a['sort_order'] ?? 0);
            $bs = (int) ($b['sort_order'] ?? 0);
            if ($as !== $bs) {
                return $bs <=> $as;
            }

            return (int) ($b['id'] ?? 0) <=> (int) ($a['id'] ?? 0);
        });

        return $levels[0];
    }

    private static function levelForUser(int $user_id): ?array
    {
        if ($user_id <= 0) {
            return null;
        }

        global $wpdb;
        $members = self::table('group_members');
        $groups = self::table('groups');

        if (!self::tableExists($members) || !self::tableExists($groups)) {
            return self::levelFromUserMeta($user_id);
        }

        $member_cols = self::columns($members);
        $group_cols = self::columns($groups);
        if (!in_array('school_level_id', $group_cols, true)) {
            return self::levelFromUserMeta($user_id);
        }

        $has_override = in_array('school_level_id_override', $member_cols, true);
        $level_expr = $has_override ? 'COALESCE(gm.school_level_id_override, g.school_level_id)' : 'g.school_level_id';
        $role_filter = in_array('role', $member_cols, true) ? "AND gm.role = 'student'" : '';
        $status_order = in_array('status', $group_cols, true) ? "CASE WHEN g.status = 'active' THEN 0 ELSE 1 END ASC," : '';
        $year_order = in_array('year_id', $group_cols, true) ? 'g.year_id DESC,' : '';

        $level_id = (int) $wpdb->get_var($wpdb->prepare("
            SELECT {$level_expr} AS level_id
            FROM {$members} gm
            INNER JOIN {$groups} g ON g.id = gm.group_id
            WHERE gm.user_id = %d
              {$role_filter}
              AND {$level_expr} IS NOT NULL
            ORDER BY {$status_order} {$year_order} gm.group_id DESC
            LIMIT 1
        ", $user_id));

        return $level_id > 0 ? self::levelById($level_id) : self::levelFromUserMeta($user_id);
    }

    private static function levelForGroup(int $group_id): ?array
    {
        if ($group_id <= 0) {
            return null;
        }

        global $wpdb;
        $groups = self::table('groups');
        if (!self::tableExists($groups)) {
            return null;
        }
        if (!in_array('school_level_id', self::columns($groups), true)) {
            return null;
        }

        $level_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT school_level_id FROM {$groups} WHERE id = %d LIMIT 1",
            $group_id
        ));

        return $level_id > 0 ? self::levelById($level_id) : null;
    }

    private static function levelFromUserMeta(int $user_id): ?array
    {
        $slug = sanitize_key((string) get_user_meta($user_id, 'nsi_level', true));
        return $slug !== '' ? self::levelBySlug($slug) : null;
    }

    private static function levelsForExercise(int $exercise_id): array
    {
        if ($exercise_id <= 0) {
            return [];
        }

        global $wpdb;
        $ids = [];
        $exercise_levels = self::table('exercise_school_level');
        if (self::tableExists($exercise_levels)) {
            $ids = array_merge($ids, array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
                "SELECT school_level_id FROM {$exercise_levels} WHERE exercise_id = %d",
                $exercise_id
            ))));
        }

        $exercises = self::table('exercises');
        if (self::tableExists($exercises) && in_array('level_id', self::columns($exercises), true)) {
            $legacy_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT level_id FROM {$exercises} WHERE id = %d LIMIT 1",
                $exercise_id
            ));
            if ($legacy_id > 0) {
                $ids[] = $legacy_id;
            }
        }

        return self::levelsByIds($ids);
    }

    private static function levelsForWrittenSubject(int $subject_id): array
    {
        if ($subject_id <= 0) {
            return [];
        }

        global $wpdb;
        $table = self::table('written_subject_school_level');
        if (!self::tableExists($table)) {
            return [];
        }

        $ids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
            "SELECT school_level_id FROM {$table} WHERE subject_id = %d",
            $subject_id
        )));

        return self::levelsByIds($ids);
    }

    private static function levelsForCompetencies(array $competency_ids): array
    {
        $competency_ids = array_values(array_unique(array_filter(array_map('intval', $competency_ids))));
        if (!$competency_ids) {
            return [];
        }

        global $wpdb;
        $relation = self::table('competency_school_level');
        if (!self::tableExists($relation)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($competency_ids), '%d'));
        $ids = array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT school_level_id FROM {$relation} WHERE competency_id IN ({$placeholders})",
            $competency_ids
        )));

        return self::levelsByIds($ids);
    }

    private static function competencyIdsForExercise(int $exercise_id): array
    {
        if ($exercise_id <= 0) {
            return [];
        }

        global $wpdb;
        $table = self::table('exercise_competency');
        if (!self::tableExists($table)) {
            return [];
        }

        return array_map('intval', (array) $wpdb->get_col($wpdb->prepare(
            "SELECT competency_id FROM {$table} WHERE exercise_id = %d",
            $exercise_id
        )));
    }

    private static function competenciesByIds(array $competency_ids): array
    {
        $competency_ids = array_values(array_unique(array_filter(array_map('intval', $competency_ids))));
        if (!$competency_ids) {
            return [];
        }

        global $wpdb;
        $table = self::table('competencies');
        if (!self::tableExists($table)) {
            return [];
        }

        $cols = self::columns($table);
        $select = ['id'];
        foreach (['track', 'domain', 'domain_slug', 'competency', 'label'] as $col) {
            $select[] = in_array($col, $cols, true) ? $col : "'' AS {$col}";
        }

        $placeholders = implode(',', array_fill(0, count($competency_ids), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT ' . implode(', ', $select) . " FROM {$table} WHERE id IN ({$placeholders}) ORDER BY track ASC, domain ASC, id ASC",
            $competency_ids
        ), ARRAY_A) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $label = trim((string) (($row['label'] ?? '') ?: ($row['competency'] ?? '')));
            $out[] = [
                'id' => (int) ($row['id'] ?? 0),
                'track' => (string) ($row['track'] ?? ''),
                'domain' => (string) ($row['domain'] ?? ''),
                'domain_slug' => (string) ($row['domain_slug'] ?? ''),
                'label' => $label,
            ];
        }

        return $out;
    }

    private static function idsFromCompetencyRows(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = (int) ($row['id'] ?? $row['competency_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    private static function levelsByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }

        global $wpdb;
        $levels = self::table('school_levels');
        if (!self::tableExists($levels)) {
            return [];
        }

        $select = self::levelSelectSql('sl');
        $join = self::levelCycleJoinSql('sl');
        $order = self::levelOrderSql('sl');
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT {$select}
            FROM {$levels} sl
            {$join}
            WHERE sl.id IN ({$placeholders})
            ORDER BY {$order}
        ", $ids), ARRAY_A) ?: [];

        return array_map([self::class, 'normalizeLevel'], $rows);
    }

    private static function levelById(int $level_id): ?array
    {
        if ($level_id <= 0) {
            return null;
        }

        $levels = self::levelsByIds([$level_id]);
        return $levels[0] ?? null;
    }

    private static function levelBySlug(string $slug): ?array
    {
        $slug = sanitize_key($slug);
        if ($slug === '') {
            return null;
        }

        global $wpdb;
        $levels = self::table('school_levels');
        if (!self::tableExists($levels)) {
            return null;
        }

        $select = self::levelSelectSql('sl');
        $join = self::levelCycleJoinSql('sl');

        $row = $wpdb->get_row($wpdb->prepare("
            SELECT {$select}
            FROM {$levels} sl
            {$join}
            WHERE sl.slug = %s
            LIMIT 1
        ", $slug), ARRAY_A);

        return is_array($row) ? self::normalizeLevel($row) : null;
    }

    private static function normalizeLevel(array $row): array
    {
        $cycle_rank = (int) ($row['cycle_rank'] ?? 0);
        $sort_order = (int) ($row['sort_order'] ?? 0);

        return [
            'id' => (int) ($row['id'] ?? 0),
            'slug' => (string) ($row['slug'] ?? ''),
            'label' => (string) ($row['label'] ?? ''),
            'sort_order' => $sort_order,
            'cycle_id' => (int) ($row['cycle_id'] ?? 0),
            'cycle_label' => (string) ($row['cycle_label'] ?? ''),
            'cycle_rank' => $cycle_rank,
            'order_in_cycle' => $cycle_rank > 0 ? $cycle_rank : $sort_order,
        ];
    }

    private static function uniqueLevels(array $levels): array
    {
        $out = [];
        foreach ($levels as $level) {
            if (!is_array($level)) {
                continue;
            }

            $id = (int) ($level['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $out[$id] = self::normalizeLevel($level);
        }

        return array_values($out);
    }

    private static function publicLevel($level): ?array
    {
        if (!is_array($level) || (int) ($level['id'] ?? 0) <= 0) {
            return null;
        }

        return [
            'id' => (int) ($level['id'] ?? 0),
            'slug' => (string) ($level['slug'] ?? ''),
            'label' => (string) ($level['label'] ?? ''),
            'cycle_id' => (int) ($level['cycle_id'] ?? 0),
            'cycle_label' => (string) ($level['cycle_label'] ?? ''),
            'order_in_cycle' => (int) ($level['order_in_cycle'] ?? 0),
        ];
    }

    private static function levelSelectSql(string $alias): string
    {
        $levels = self::table('school_levels');
        $cols = self::columns($levels);

        $cycle_id = in_array('cycle_id', $cols, true) ? "{$alias}.cycle_id" : '0';
        $cycle_rank = in_array('cycle_rank', $cols, true) ? "{$alias}.cycle_rank" : '0';
        $sort_order = in_array('sort_order', $cols, true) ? "{$alias}.sort_order" : '0';
        $cycle_label = in_array('cycle_id', $cols, true) && self::tableExists(self::cyclesTable()) ? 'cy.label' : "''";

        return "{$alias}.id, {$alias}.slug, {$alias}.label, {$sort_order} AS sort_order, {$cycle_id} AS cycle_id, {$cycle_rank} AS cycle_rank, {$cycle_label} AS cycle_label";
    }

    private static function levelCycleJoinSql(string $alias): string
    {
        $levels = self::table('school_levels');
        if (!in_array('cycle_id', self::columns($levels), true) || !self::tableExists(self::cyclesTable())) {
            return '';
        }

        return 'LEFT JOIN ' . self::cyclesTable() . " cy ON cy.id = {$alias}.cycle_id";
    }

    private static function levelOrderSql(string $alias): string
    {
        $levels = self::table('school_levels');
        $cols = self::columns($levels);
        if (in_array('cycle_id', $cols, true) && in_array('cycle_rank', $cols, true)) {
            $sort = in_array('sort_order', $cols, true) ? "{$alias}.sort_order ASC, " : '';
            return "{$alias}.cycle_id ASC, {$alias}.cycle_rank ASC, {$sort}{$alias}.id ASC";
        }

        if (in_array('sort_order', $cols, true)) {
            return "{$alias}.sort_order ASC, {$alias}.id ASC";
        }

        return "{$alias}.id ASC";
    }

    private static function cumulativeLevelsEnabled(): bool
    {
        return (bool) apply_filters(
            'ouinpo_exercises_cumulative_school_levels',
            (bool) get_option('ouinpo_exercises_cumulative_school_levels', false)
        );
    }

    private static function table(string $suffix): string
    {
        global $wpdb;
        return $wpdb->prefix . 'ouin_exo_' . $suffix;
    }

    private static function cyclesTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . 'ouinpo_cycles';
    }

    private static function tableExists(string $table): bool
    {
        global $wpdb;
        return $table !== '' && (string) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
    }

    private static function columns(string $table): array
    {
        static $cache = [];

        if ($table === '') {
            return [];
        }

        if (isset($cache[$table])) {
            return $cache[$table];
        }

        global $wpdb;
        if (!self::tableExists($table)) {
            $cache[$table] = [];
            return [];
        }

        $cache[$table] = array_map('strval', (array) $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0));
        return $cache[$table];
    }
}
