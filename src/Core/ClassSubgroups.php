<?php
namespace Ouinpo\Suite\Core;

/** Optional subdivisions; class membership remains the authority for access. */
final class ClassSubgroups
{
    public static function all(int $classId): array
    {
        $groups = get_option('ouinpo_class_subgroups_' . $classId, []);
        return is_array($groups) ? $groups : [];
    }

    public static function save(int $classId, array $groups): void
    {
        update_option('ouinpo_class_subgroups_' . $classId, $groups, false);
    }

    public static function allows(int $userId, array $classIds, array $targets): bool
    {
        foreach ($classIds as $classId) {
            foreach (self::all((int) $classId) as $id => $group) {
                if (in_array($classId . ':' . $id, $targets, true)
                    && in_array($userId, array_map('intval', $group['members']), true)) {
                    return true;
                }
            }
        }
        return false;
    }
}
