<?php

namespace Ouinpo\Flashcards;



defined('ABSPATH') || exit;



final class Service

{

    private const GOOD_INTERVALS = [1 => 1, 2 => 3, 3 => 7, 4 => 15, 5 => 30];

    private const HARD_INTERVALS = [1 => 1, 2 => 2, 3 => 4, 4 => 7, 5 => 15];



    public static function table(string $suffix): string

    {

        global $wpdb;

        return $wpdb->prefix . 'ouin_fc_' . $suffix;

    }



    public static function exoTable(string $suffix): string

    {

        global $wpdb;

        return $wpdb->prefix . 'ouin_exo_' . $suffix;

    }

    private static function canSeeAllCards(int $user_id): bool
    {
        if ($user_id <= 0) {
            return false;
        }

        return user_can($user_id, 'manage_options')
            || user_can($user_id, 'edit_users');
    }

    public static function current_year_id(): ?int

    {

        global $wpdb;

        $year = (int) $wpdb->get_var("SELECT id FROM " . self::exoTable('academic_years') . " WHERE is_active = 1 ORDER BY id DESC LIMIT 1");

        return $year ?: null;

    }



    public static function current_user_level_label(int $user_id): ?string

    {

        global $wpdb;

        $year_id = self::current_year_id();

        if (!$year_id) {

            return null;

        }



        $groups = self::exoTable('groups');

        $gm = self::exoTable('group_members');

        $levels = self::exoTable('school_levels');



        $label = $wpdb->get_var($wpdb->prepare(

            "SELECT sl.label

               FROM {$gm} gm

               JOIN {$groups} g ON g.id = gm.group_id

               JOIN {$levels} sl ON sl.id = COALESCE(gm.school_level_id_override, g.school_level_id)

              WHERE gm.user_id = %d AND g.year_id = %d

              ORDER BY gm.group_id DESC

              LIMIT 1",

            $user_id,

            $year_id

        ));



        return $label ? (string) $label : null;

    }



    public static function level_slug_to_label(string $slug): string
    {
        $raw = trim($slug);

        if (in_array($raw, ['Seconde', 'Première', 'Terminale', 'Transversal'], true)) {
            return $raw;
        }

        $key = function_exists('remove_accents')
            ? remove_accents($raw)
            : $raw;

        $key = strtolower(trim($key));
        $key = str_replace([' ', '_'], '-', $key);
        $key = sanitize_key($key);

        return match ($key) {
            'seconde', '2nde' => 'Seconde',
            'premiere', 'premiere-nsi', '1ere', '1re' => 'Première',
            'terminale', 'term', 'tle' => 'Terminale',
            'transversal', 'transverse' => 'Transversal',
            default => $raw,
        };
    }





    public static function normalize_deck_ids($value): array

    {

        if ($value === null || $value === '') {

            return [];

        }



        if (is_array($value)) {

            $parts = $value;

        } else {

            $parts = preg_split('/[,\s;]+/', (string) $value);

        }



        $ids = [];

        foreach ($parts ?: [] as $part) {

            $id = (int) $part;

            if ($id > 0) {

                $ids[$id] = $id;

            }

        }



        return array_values($ids);

    }



    private static function current_user_group_context(int $user_id): array

    {

        global $wpdb;



        $year_id = self::current_year_id();

        if (self::canSeeAllCards($user_id)) {
            return [
                'year_id' => $year_id,
                'group_id' => null,
                'level' => null,
            ];
        }        

        if (!$year_id || !$user_id) {

            return [

                'year_id' => $year_id,

                'group_id' => null,

                'level' => null,

            ];

        }



        $groups = self::exoTable('groups');

        $gm = self::exoTable('group_members');

        $levels = self::exoTable('school_levels');



        $row = $wpdb->get_row($wpdb->prepare(

            "SELECT

                gm.group_id,

                sl.label AS level_label

             FROM {$gm} gm

             JOIN {$groups} g ON g.id = gm.group_id

             JOIN {$levels} sl ON sl.id = COALESCE(gm.school_level_id_override, g.school_level_id)

             WHERE gm.user_id = %d

               AND g.year_id = %d

             ORDER BY gm.group_id DESC

             LIMIT 1",

            $user_id,

            $year_id

        ), ARRAY_A);



        return [

            'year_id' => $year_id,

            'group_id' => $row ? (int) $row['group_id'] : null,

            'level' => $row && !empty($row['level_label']) ? (string) $row['level_label'] : null,

        ];

    }



    private static function requested_level(int $user_id, array $args = []): ?string
    {
        if (!empty($args['level'])) {
            return self::level_slug_to_label((string) $args['level']);
        }

        if (self::canSeeAllCards($user_id)) {
            return null;
        }

        return self::current_user_level_label($user_id);
    }



    private static function requested_track(array $args = []): ?string

    {

        $track = !empty($args['track']) ? strtoupper((string) $args['track']) : null;

        return in_array($track, ['SNT', 'NSI'], true) ? $track : null;

    }



    private static function add_in_clause(string $field, array $ids, array &$where, array &$params): void

    {

        $ids = self::normalize_deck_ids($ids);

        if (!$ids) {

            return;

        }



        $where[] = $field . ' IN (' . implode(',', array_fill(0, count($ids), '%d')) . ')';

        foreach ($ids as $id) {

            $params[] = $id;

        }

    }



    private static function visible_card_condition(string $cardAlias, int $user_id, ?string $domain_slug, array &$params): string

    {

        $cc = self::table('card_competency');

        $comp = self::exoTable('competencies');

        $teaching = self::exoTable('competency_teaching');



        $conditions = [];



        if ($domain_slug !== null && $domain_slug !== '') {

            $conditions[] = "EXISTS (

                SELECT 1

                  FROM {$cc} cc_domain

                  JOIN {$comp} comp_domain ON comp_domain.id = cc_domain.competency_id

                 WHERE cc_domain.card_id = {$cardAlias}.id

                   AND comp_domain.domain_slug = %s

                   AND IFNULL(comp_domain.active, 1) = 1

            )";

            $params[] = $domain_slug;

        }



        $ctx = self::current_user_group_context($user_id);

        if (!empty($ctx['year_id']) && !empty($ctx['group_id'])) {

            $conditions[] = "(

                NOT EXISTS (

                    SELECT 1

                      FROM {$cc} cc_any

                     WHERE cc_any.card_id = {$cardAlias}.id

                )

                OR EXISTS (

                    SELECT 1

                      FROM {$cc} cc_seen

                      JOIN {$teaching} teach

                        ON teach.competency_id = cc_seen.competency_id

                     WHERE cc_seen.card_id = {$cardAlias}.id

                       AND teach.year_id = %d

                       AND teach.group_id = %d

                       AND teach.teaching_state = 'seen'

                )

            )";

            $params[] = (int) $ctx['year_id'];

            $params[] = (int) $ctx['group_id'];

        }



        return $conditions ? (' AND ' . implode(' AND ', $conditions)) : '';

    }



    public static function get_accessible_domains(int $user_id, array $args = []): array

    {

        global $wpdb;



        $decks = self::table('decks');

        $cards = self::table('cards');

        $card_comp = self::table('card_competency');

        $competencies = self::exoTable('competencies');

        $teaching = self::exoTable('competency_teaching');



        $level = self::requested_level($user_id, $args);

        $track = self::requested_track($args);

        $ctx = self::current_user_group_context($user_id);



        $where = [

            'd.is_active = 1',

            'c.is_active = 1',

            "comp.domain_slug IS NOT NULL",

            "comp.domain_slug <> ''",

            'IFNULL(comp.active, 1) = 1',

        ];

        $params = [];



        if ($level) {

            $where[] = "(d.level = %s OR d.level = 'Transversal')";

            $params[] = $level;

        }



        if ($track) {

            $where[] = 'd.track = %s';

            $params[] = $track;

        }



        if (!empty($ctx['year_id']) && !empty($ctx['group_id'])) {

            $where[] = "EXISTS (

                SELECT 1

                  FROM {$teaching} teach

                 WHERE teach.competency_id = comp.id

                   AND teach.year_id = %d

                   AND teach.group_id = %d

                   AND teach.teaching_state = 'seen'

            )";

            $params[] = (int) $ctx['year_id'];

            $params[] = (int) $ctx['group_id'];

        }



        $sql = "SELECT

                    comp.domain_slug,

                    MIN(comp.domain) AS domain,

                    COUNT(DISTINCT c.id) AS total_cards

                FROM {$decks} d

                JOIN {$cards} c ON c.deck_id = d.id

                JOIN {$card_comp} cc ON cc.card_id = c.id

                JOIN {$competencies} comp ON comp.id = cc.competency_id

                WHERE " . implode(' AND ', $where) . "

                GROUP BY comp.domain_slug

                ORDER BY domain";



        if ($params) {

            $sql = $wpdb->prepare($sql, ...$params);

        }



        $rows = $wpdb->get_results($sql, ARRAY_A);

        foreach ($rows as &$row) {

            $row['total_cards'] = (int) $row['total_cards'];

        }



        return $rows ?: [];

    }



    public static function get_accessible_decks(int $user_id, array $args = []): array

    {

        global $wpdb;



        $decks = self::table('decks');

        $cards = self::table('cards');

        $user_cards = self::table('user_cards');

        $card_comp = self::table('card_competency');

        $competencies = self::exoTable('competencies');



        $level = self::requested_level($user_id, $args);

        $track = self::requested_track($args);

        $domain_slug = !empty($args['domain_slug']) ? sanitize_title((string) $args['domain_slug']) : null;

        $deck_ids = self::normalize_deck_ids($args['deck_ids'] ?? []);



        $where = ["d.is_active = 1"];

        $params = [];



        if ($level) {

            $where[] = "(d.level = %s OR d.level = 'Transversal')";

            $params[] = $level;

        }



        if ($track) {

            $where[] = 'd.track = %s';

            $params[] = $track;

        }



        self::add_in_clause('d.id', $deck_ids, $where, $params);



        $visibleParams = [];

        $visibleCondition = self::visible_card_condition('c_scope', $user_id, $domain_slug, $visibleParams);



        $where[] = "EXISTS (

            SELECT 1

              FROM {$cards} c_scope

             WHERE c_scope.deck_id = d.id

               AND c_scope.is_active = 1

               {$visibleCondition}

        )";

        foreach ($visibleParams as $p) {

            $params[] = $p;

        }



        $sql = "SELECT

                    d.id,

                    d.title,

                    d.slug,

                    d.description,

                    d.track,

                    d.level,

                    d.source_post_id,

                    COUNT(DISTINCT c.id) AS total_cards,

                    SUM(CASE WHEN uc.next_review_at IS NOT NULL AND uc.next_review_at <= NOW() AND uc.status <> 'suspended' THEN 1 ELSE 0 END) AS due_cards,

                    SUM(CASE WHEN uc.status IN ('review','mastered') THEN 1 ELSE 0 END) AS mastered_cards,

                    SUM(CASE WHEN uc.status IN ('learning','review','mastered') THEN 1 ELSE 0 END) AS seen_cards

                FROM {$decks} d

                LEFT JOIN {$cards} c

                       ON c.deck_id = d.id AND c.is_active = 1

                LEFT JOIN {$user_cards} uc

                       ON uc.card_id = c.id AND uc.user_id = %d

                WHERE " . implode(' AND ', $where) . "

                GROUP BY d.id

                ORDER BY d.level, d.track, d.title";



        $finalParams = [];

        $finalParams[] = $user_id;

        foreach ($params as $p) {

            $finalParams[] = $p;

        }



        $sql = $wpdb->prepare($sql, ...$finalParams);

        $rows = $wpdb->get_results($sql, ARRAY_A);



        foreach ($rows as &$row) {

            $row['id'] = (int) $row['id'];

            $row['total_cards'] = (int) $row['total_cards'];

            $row['due_cards'] = (int) $row['due_cards'];

            $row['mastered_cards'] = (int) $row['mastered_cards'];

            $row['seen_cards'] = (int) $row['seen_cards'];

        }



        return $rows ?: [];

    }



    public static function get_due_counts(int $user_id, $deck_ids = null, ?string $domain_slug = null, array $args = []): array

    {

        global $wpdb;



        $cards = self::table('cards');

        $decks = self::table('decks');

        $user_cards = self::table('user_cards');



        $deckIds = is_int($deck_ids) ? ($deck_ids > 0 ? [$deck_ids] : []) : self::normalize_deck_ids($deck_ids);

        $domain_slug = $domain_slug ? sanitize_title($domain_slug) : null;

        $level = self::requested_level($user_id, $args);

        $track = self::requested_track($args);



        $where = [

            'c.is_active = 1',

            'd.is_active = 1',

            "uc.status <> 'suspended'",

        ];

        $params = [$user_id];



        self::add_in_clause('d.id', $deckIds, $where, $params);



        if (!$deckIds && $level) {

            $where[] = "(d.level = %s OR d.level = 'Transversal')";

            $params[] = $level;

        }



        if ($track) {

            $where[] = 'd.track = %s';

            $params[] = $track;

        }



        $whereExtraParams = [];

        $visibleCondition = self::visible_card_condition('c', $user_id, $domain_slug, $whereExtraParams);

        foreach ($whereExtraParams as $p) {

            $params[] = $p;

        }



        $sqlDue = "SELECT COUNT(DISTINCT c.id)

                     FROM {$user_cards} uc

                     JOIN {$cards} c ON c.id = uc.card_id

                     JOIN {$decks} d ON d.id = c.deck_id

                    WHERE uc.user_id = %d

                      AND uc.next_review_at IS NOT NULL

                      AND uc.next_review_at <= NOW()

                      AND " . implode(' AND ', $where) . $visibleCondition;



        $due = (int) $wpdb->get_var($wpdb->prepare($sqlDue, ...$params));



        $whereNew = ['c.is_active = 1', 'd.is_active = 1'];

        $paramsNew = [];



        self::add_in_clause('d.id', $deckIds, $whereNew, $paramsNew);



        if (!$deckIds && $level) {

            $whereNew[] = "(d.level = %s OR d.level = 'Transversal')";

            $paramsNew[] = $level;

        }



        if ($track) {

            $whereNew[] = 'd.track = %s';

            $paramsNew[] = $track;

        }



        $whereNewExtraParams = [];

        $visibleNewCondition = self::visible_card_condition('c', $user_id, $domain_slug, $whereNewExtraParams);

        foreach ($whereNewExtraParams as $p) {

            $paramsNew[] = $p;

        }



        $sqlNew = "SELECT COUNT(DISTINCT c.id)

                     FROM {$cards} c

                     JOIN {$decks} d ON d.id = c.deck_id

                LEFT JOIN {$user_cards} uc ON uc.card_id = c.id AND uc.user_id = %d

                    WHERE uc.card_id IS NULL

                      AND " . implode(' AND ', $whereNew) . $visibleNewCondition;



        array_unshift($paramsNew, $user_id);

        $new = (int) $wpdb->get_var($wpdb->prepare($sqlNew, ...$paramsNew));



        return ['due' => $due, 'new' => $new];

    }



    public static function get_next_card_for_user(int $user_id, $deck_ids = null, ?string $domain_slug = null, array $args = []): ?array

    {

        global $wpdb;



        $cards = self::table('cards');

        $decks = self::table('decks');

        $user_cards = self::table('user_cards');

        $card_comp = self::table('card_competency');

        $competencies = self::exoTable('competencies');



        $deckIds = is_int($deck_ids) ? ($deck_ids > 0 ? [$deck_ids] : []) : self::normalize_deck_ids($deck_ids);

        $domain_slug = $domain_slug ? sanitize_title($domain_slug) : null;

        $level = self::requested_level($user_id, $args);

        $track = self::requested_track($args);



        $scopeWhere = [];

        $scopeParams = [];



        self::add_in_clause('d.id', $deckIds, $scopeWhere, $scopeParams);



        if (!$deckIds && $level) {

            $scopeWhere[] = "(d.level = %s OR d.level = 'Transversal')";

            $scopeParams[] = $level;

        }



        if ($track) {

            $scopeWhere[] = 'd.track = %s';

            $scopeParams[] = $track;

        }



        $visibleParams = [];

        $visibleCondition = self::visible_card_condition('c', $user_id, $domain_slug, $visibleParams);

        foreach ($visibleParams as $p) {

            $scopeParams[] = $p;

        }



        $scopeSql = $scopeWhere ? (' AND ' . implode(' AND ', $scopeWhere)) : '';



        $paramsDue = [$user_id];

        foreach ($scopeParams as $p) {

            $paramsDue[] = $p;

        }



        $sqlDue = "SELECT

                      c.id,

                      c.deck_id,

                      c.card_type,

                      c.front_html,

                      c.back_html,

                      d.title AS deck_title,

                      d.slug AS deck_slug,

                      d.level,

                      d.track,

                      uc.status,

                      uc.box,

                      uc.next_review_at,

                      GROUP_CONCAT(DISTINCT comp.slug ORDER BY comp.slug SEPARATOR ', ') AS competency_slugs

                    FROM {$user_cards} uc

                    JOIN {$cards} c ON c.id = uc.card_id AND c.is_active = 1

                    JOIN {$decks} d ON d.id = c.deck_id AND d.is_active = 1

               LEFT JOIN {$card_comp} cc ON cc.card_id = c.id

               LEFT JOIN {$competencies} comp ON comp.id = cc.competency_id

                   WHERE uc.user_id = %d

                     AND uc.status <> 'suspended'

                     AND uc.next_review_at IS NOT NULL

                     AND uc.next_review_at <= NOW()

                     {$scopeSql}

                     {$visibleCondition}

                GROUP BY c.id

                ORDER BY uc.next_review_at ASC, c.sort_order ASC, c.id ASC

                   LIMIT 1";



        $row = $wpdb->get_row($wpdb->prepare($sqlDue, ...$paramsDue), ARRAY_A);

        if ($row) {

            return self::normalize_card_row($row, 'due');

        }



        $paramsNew = [$user_id];

        foreach ($scopeParams as $p) {

            $paramsNew[] = $p;

        }



        $sqlNew = "SELECT

                      c.id,

                      c.deck_id,

                      c.card_type,

                      c.front_html,

                      c.back_html,

                      d.title AS deck_title,

                      d.slug AS deck_slug,

                      d.level,

                      d.track,

                      'new' AS status,

                      1 AS box,

                      NULL AS next_review_at,

                      GROUP_CONCAT(DISTINCT comp.slug ORDER BY comp.slug SEPARATOR ', ') AS competency_slugs

                    FROM {$cards} c

                    JOIN {$decks} d ON d.id = c.deck_id AND d.is_active = 1

               LEFT JOIN {$card_comp} cc ON cc.card_id = c.id

               LEFT JOIN {$competencies} comp ON comp.id = cc.competency_id

               LEFT JOIN {$user_cards} uc ON uc.card_id = c.id AND uc.user_id = %d

                   WHERE c.is_active = 1

                     AND uc.card_id IS NULL

                     {$scopeSql}

                     {$visibleCondition}

                GROUP BY c.id

                ORDER BY c.sort_order ASC, c.id ASC

                   LIMIT 1";



        $row = $wpdb->get_row($wpdb->prepare($sqlNew, ...$paramsNew), ARRAY_A);

        return $row ? self::normalize_card_row($row, 'new') : null;

    }



    private static function normalize_card_row(array $row, string $queue): array

    {

        $row['id'] = (int) $row['id'];

        $row['deck_id'] = (int) $row['deck_id'];

        $row['box'] = (int) $row['box'];

        $row['queue'] = $queue;

        $row['competency_slugs'] = array_values(array_filter(array_map('trim', explode(',', (string) ($row['competency_slugs'] ?? '')))));

        return $row;

    }



    public static function review_card(int $user_id, int $card_id, string $grade): array

    {

        global $wpdb;



        $grade = strtolower(trim($grade));

        if (!in_array($grade, ['again', 'hard', 'good'], true)) {

            throw new \InvalidArgumentException('Grade invalide.');

        }



        $cards = self::table('cards');

        $user_cards = self::table('user_cards');

        $reviews = self::table('reviews');



        $cardExists = (int) $wpdb->get_var($wpdb->prepare(

            "SELECT COUNT(*) FROM {$cards} WHERE id = %d AND is_active = 1",

            $card_id

        ));

        if (!$cardExists) {

            throw new \RuntimeException('Carte introuvable.');

        }



        $state = $wpdb->get_row($wpdb->prepare(

            "SELECT * FROM {$user_cards} WHERE user_id = %d AND card_id = %d LIMIT 1",

            $user_id,

            $card_id

        ), ARRAY_A);



        $oldBox = $state ? max(1, (int) $state['box']) : 1;

        $status = $state['status'] ?? 'new';

        $success = $state ? (int) $state['success_streak'] : 0;

        $lapses = $state ? (int) $state['lapse_count'] : 0;

        $seen = $state ? (int) $state['seen_count'] : 0;



        if ($grade === 'again') {

            $newBox = 1;

            $next = self::date_in_days(1);

            $newStatus = 'learning';

            $success = 0;

            $lapses++;

        } elseif ($grade === 'hard') {

            $newBox = $oldBox;

            $next = self::date_in_days(self::HARD_INTERVALS[$newBox] ?? 2);

            $newStatus = ($newBox >= 5 && $success >= 3) ? 'mastered' : 'review';

        } else {

            $newBox = min(5, $oldBox + 1);

            $next = self::date_in_days(self::GOOD_INTERVALS[$newBox] ?? 3);

            $success++;

            $newStatus = ($newBox >= 5 && $success >= 3) ? 'mastered' : 'review';

        }



        $seen++;

        $now = current_time('mysql');



        if ($state) {

            $wpdb->update(

                $user_cards,

                [

                    'status' => $newStatus,

                    'box' => $newBox,

                    'success_streak' => $success,

                    'lapse_count' => $lapses,

                    'seen_count' => $seen,

                    'last_review_at' => $now,

                    'next_review_at' => $next,

                    'last_grade' => $grade,

                ],

                ['user_id' => $user_id, 'card_id' => $card_id],

                ['%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s'],

                ['%d', '%d']

            );

        } else {

            $wpdb->insert(

                $user_cards,

                [

                    'user_id' => $user_id,

                    'card_id' => $card_id,

                    'status' => $newStatus,

                    'box' => $newBox,

                    'success_streak' => $success,

                    'lapse_count' => $lapses,

                    'seen_count' => $seen,

                    'last_review_at' => $now,

                    'next_review_at' => $next,

                    'last_grade' => $grade,

                ],

                ['%d', '%d', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s']

            );

        }



        $wpdb->insert(

            $reviews,

            [

                'user_id' => $user_id,

                'card_id' => $card_id,

                'grade' => $grade,

                'old_box' => $oldBox,

                'new_box' => $newBox,

            ],

            ['%d', '%d', '%s', '%d', '%d']

        );



        return [

            'old_box' => $oldBox,

            'new_box' => $newBox,

            'next_review_at' => $next,

            'status' => $newStatus,

            'success_streak' => $success,

            'lapse_count' => $lapses,

            'seen_count' => $seen,

        ];

    }



    public static function deck_options(): array

    {

        global $wpdb;

        return $wpdb->get_results(

            "SELECT id, title, slug, track, level, is_active

               FROM " . self::table('decks') . "

              ORDER BY level, track, title",

            ARRAY_A

        );

    }



    public static function get_deck(int $deck_id): ?array

    {

        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(

            "SELECT * FROM " . self::table('decks') . " WHERE id = %d LIMIT 1",

            $deck_id

        ), ARRAY_A);

        return $row ?: null;

    }



    public static function get_cards_for_deck(int $deck_id): array

    {

        global $wpdb;

        $cards = self::table('cards');

        $cc = self::table('card_competency');

        $comp = self::exoTable('competencies');



        $rows = $wpdb->get_results($wpdb->prepare(

            "SELECT

                c.*,

                GROUP_CONCAT(DISTINCT comp.slug ORDER BY comp.slug SEPARATOR ', ') AS competency_slugs

             FROM {$cards} c

        LEFT JOIN {$cc} cc ON cc.card_id = c.id

        LEFT JOIN {$comp} comp ON comp.id = cc.competency_id

            WHERE c.deck_id = %d

            GROUP BY c.id

            ORDER BY c.sort_order ASC, c.id ASC",

            $deck_id

        ), ARRAY_A);



        return array_map(function(array $row) {

            $row['id'] = (int) $row['id'];

            $row['deck_id'] = (int) $row['deck_id'];

            $row['sort_order'] = (int) $row['sort_order'];

            $row['is_active'] = (int) $row['is_active'];

            return $row;

        }, $rows);

    }



    public static function get_card(int $card_id): ?array

    {

        global $wpdb;

        $cards = self::table('cards');

        $cc = self::table('card_competency');

        $comp = self::exoTable('competencies');



        $row = $wpdb->get_row($wpdb->prepare(

            "SELECT

                c.*,

                GROUP_CONCAT(DISTINCT comp.slug ORDER BY comp.slug SEPARATOR ', ') AS competency_slugs

             FROM {$cards} c

        LEFT JOIN {$cc} cc ON cc.card_id = c.id

        LEFT JOIN {$comp} comp ON comp.id = cc.competency_id

            WHERE c.id = %d

            GROUP BY c.id

            LIMIT 1",

            $card_id

        ), ARRAY_A);

        return $row ?: null;

    }



private static function resolve_source_post_id_from_slug(string $slug): ?int

{

    $slug = sanitize_title($slug);



    if ($slug === '') {

        return null;

    }



    $post = get_page_by_path($slug, OBJECT, ['post', 'page']);



    if ($post instanceof \WP_Post) {

        return (int) $post->ID;

    }



    return null;

}



public static function save_deck(array $data): int

{

    global $wpdb;

    $table = self::table('decks');



    $title = sanitize_text_field($data['title'] ?? '');

    $slug = sanitize_title($data['slug'] ?? $title ?? '');

    $description = wp_kses_post($data['description'] ?? '');

    $track = in_array(($data['track'] ?? 'NSI'), ['SNT', 'NSI'], true) ? $data['track'] : 'NSI';

    $level = self::normalize_level($data['level'] ?? 'Première');



    $source_post_slug = sanitize_title($data['source_post_slug'] ?? '');

    $source_post_id = self::resolve_source_post_id_from_slug($source_post_slug);



    if ($source_post_slug !== '' && !$source_post_id) {

        throw new \RuntimeException('Aucun article ou page trouvé pour ce slug de post source.');

    }



    $payload = [

        'title' => $title,

        'slug' => $slug,

        'description' => $description,

        'track' => $track,

        'level' => $level,

        'source_post_id' => $source_post_id,

        'is_active' => !empty($data['is_active']) ? 1 : 0,

    ];



    if (empty($payload['title'])) {

        throw new \RuntimeException('Le titre du paquet est obligatoire.');

    }

    if (empty($payload['slug'])) {

        throw new \RuntimeException('Le slug du paquet est obligatoire.');

    }



    $deck_id = !empty($data['id']) ? (int) $data['id'] : 0;



    if ($deck_id > 0) {

        $wpdb->update($table, $payload, ['id' => $deck_id]);

        return $deck_id;

    }



    $wpdb->insert($table, $payload);

    return (int) $wpdb->insert_id;

}



    public static function delete_deck(int $deck_id): void

    {

        global $wpdb;

        $wpdb->delete(self::table('decks'), ['id' => $deck_id], ['%d']);

    }



    public static function save_card(array $data): int

    {

        global $wpdb;

        $table = self::table('cards');



        $payload = [

            'deck_id' => (int) ($data['deck_id'] ?? 0),

            'front_html' => wp_kses_post($data['front_html'] ?? ''),

            'back_html' => wp_kses_post($data['back_html'] ?? ''),

            'note_teacher' => sanitize_textarea_field($data['note_teacher'] ?? ''),

            'card_type' => self::normalize_card_type($data['card_type'] ?? 'definition'),

            'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : 0,

            'is_active' => !empty($data['is_active']) ? 1 : 0,

        ];



        if ($payload['deck_id'] <= 0) {

            throw new \RuntimeException('Le deck est obligatoire.');

        }

        if (trim(wp_strip_all_tags($payload['front_html'])) === '' || trim(wp_strip_all_tags($payload['back_html'])) === '') {

            throw new \RuntimeException('Le recto et le verso sont obligatoires.');

        }



        $card_id = !empty($data['id']) ? (int) $data['id'] : 0;

        if ($card_id > 0) {

            $wpdb->update($table, $payload, ['id' => $card_id]);

        } else {

            $wpdb->insert($table, $payload);

            $card_id = (int) $wpdb->insert_id;

        }



        self::save_card_competencies($card_id, (string) ($data['competency_slugs'] ?? ''));

        return $card_id;

    }



    public static function delete_card(int $card_id): void

    {

        global $wpdb;

        $wpdb->delete(self::table('cards'), ['id' => $card_id], ['%d']);

    }



public static function import_cards_from_csv(int $deck_id, string $csv): int

{

    $csv = trim($csv);



    if ($csv === '') {

        return 0;

    }



    // Supprime un éventuel BOM UTF-8

    $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);



    $lines = preg_split('/\R/u', $csv);

    if (!$lines) {

        return 0;

    }



    $count = 0;

    $isFirstLine = true;



    foreach ($lines as $line) {

        $line = trim($line);



        if ($line === '' || str_starts_with($line, '#')) {

            continue;

        }



        $cols = str_getcsv($line, ';');



        // Ignore l’en-tête CSV

        if (

            $isFirstLine

            && isset($cols[0], $cols[1], $cols[2])

            && strtolower(trim((string) $cols[0])) === 'type'

            && strtolower(trim((string) $cols[1])) === 'front_html'

            && strtolower(trim((string) $cols[2])) === 'back_html'

        ) {

            $isFirstLine = false;

            continue;

        }



        $isFirstLine = false;



        $type = $cols[0] ?? 'definition';

        $front = $cols[1] ?? '';

        $back = $cols[2] ?? '';

        $sort = $cols[3] ?? 0;

        $slugs = $cols[4] ?? '';



        self::save_card([

            'deck_id' => $deck_id,

            'card_type' => $type,

            'front_html' => $front,

            'back_html' => $back,

            'sort_order' => $sort,

            'competency_slugs' => $slugs,

            'is_active' => 1,

        ]);



        $count++;

    }



    return $count;

}



    public static function stats(): array

    {

        global $wpdb;

        $decks = self::table('decks');

        $cards = self::table('cards');

        $reviews = self::table('reviews');

        $user_cards = self::table('user_cards');



        return [

            'decks' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$decks}"),

            'cards' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$cards}"),

            'reviews' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$reviews}"),

            'due_today' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$user_cards} WHERE next_review_at IS NOT NULL AND next_review_at <= NOW() AND status <> 'suspended'"),

        ];

    }



    private static function save_card_competencies(int $card_id, string $slugList): void

    {

        global $wpdb;

        $pivot = self::table('card_competency');

        $wpdb->delete($pivot, ['card_id' => $card_id], ['%d']);



        $slugs = preg_split('/[\s,;]+/u', trim($slugList));

        $slugs = array_values(array_unique(array_filter(array_map('trim', $slugs ?: []))));

        if (!$slugs) {

            return;

        }



        $compTable = self::exoTable('competencies');

        foreach ($slugs as $slug) {

            $compId = (int) $wpdb->get_var($wpdb->prepare(

                "SELECT id FROM {$compTable} WHERE slug = %s LIMIT 1",

                $slug

            ));

            if ($compId > 0) {

                $wpdb->insert($pivot, ['card_id' => $card_id, 'competency_id' => $compId], ['%d', '%d']);

            }

        }

    }



    private static function normalize_level(string $level): string

    {

        $level = trim($level);

        $allowed = ['Seconde', 'Première', 'Terminale', 'Transversal'];

        if (in_array($level, $allowed, true)) {

            return $level;

        }

        return self::level_slug_to_label($level);

    }



    private static function normalize_card_type(string $type): string

    {

        $type = strtolower(trim($type));

        $allowed = ['definition', 'distinction', 'repere', 'syntaxe', 'vocabulaire'];

        return in_array($type, $allowed, true) ? $type : 'definition';

    }



    private static function date_in_days(int $days): string

    {

        return wp_date('Y-m-d H:i:s', current_time('timestamp') + max(1, $days) * DAY_IN_SECONDS);

    }

}

