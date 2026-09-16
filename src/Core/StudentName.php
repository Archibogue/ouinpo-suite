<?php
namespace Ouinpo\Suite\Core;

/** Shared student labels without modifying WordPress profiles. */
final class StudentName
{
    public static function format(object $user): string
    {
        $id = (int) ($user->ID ?? $user->id ?? 0);
        $last = trim((string) get_user_meta($id, 'last_name', true));
        $first = trim((string) get_user_meta($id, 'first_name', true));
        $name = trim($last . ' ' . $first);
        return $name !== '' ? $name : (string) ($user->display_name ?? $user->user_login ?? '');
    }

    /** Apply ordering before LIMIT so every page follows the same name order. */
    public static function getUsers(array $args = []): array
    {
        global $wpdb;
        $args['count_total'] = false;
        $query = new \WP_User_Query();
        $query->prepare_query($args);
        $query->query_orderby = 'ORDER BY ' . self::sql($wpdb->users) . ' ASC, ' . $wpdb->users . '.ID ASC';
        $query->query();
        return $query->get_results();
    }

    public static function compare(string $left, string $right): int
    {
        return strnatcasecmp(remove_accents($left), remove_accents($right));
    }

    /** SQL equivalent for lists loaded directly through wpdb. */
    public static function sql(string $alias = 'u'): string
    {
        global $wpdb;
        if (!preg_match('/^[A-Za-z0-9_]+$/', $alias)) {
            throw new \InvalidArgumentException('Invalid user table alias');
        }
        $alias = '`' . $alias . '`';
        $last = "(SELECT TRIM(sn_last.meta_value) FROM {$wpdb->usermeta} sn_last WHERE sn_last.user_id = {$alias}.ID AND sn_last.meta_key = 'last_name' ORDER BY sn_last.umeta_id ASC LIMIT 1)";
        $first = "(SELECT TRIM(sn_first.meta_value) FROM {$wpdb->usermeta} sn_first WHERE sn_first.user_id = {$alias}.ID AND sn_first.meta_key = 'first_name' ORDER BY sn_first.umeta_id ASC LIMIT 1)";
        return "COALESCE(NULLIF(TRIM(CONCAT(COALESCE({$last}, ''), ' ', COALESCE({$first}, ''))), ''), {$alias}.display_name)";
    }
}
