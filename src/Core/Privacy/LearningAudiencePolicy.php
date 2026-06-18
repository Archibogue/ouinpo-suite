<?php
namespace Ouinpo\Suite\Core\Privacy;

use Ouinpo\Suite\Core\Capabilities;

defined('ABSPATH') || exit;

final class LearningAudiencePolicy
{
    public static function isAutonomousLearner(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $user = get_user_by('id', $userId);
        if (!$user) {
            return false;
        }

        return in_array('ouinpo_learner', (array) $user->roles, true);
    }

    public static function isClassStudent(int $userId): bool
    {
        if ($userId <= 0 || self::isAutonomousLearner($userId)) {
            return false;
        }

        $user = get_user_by('id', $userId);
        if (!$user) {
            return false;
        }

        return in_array('ouinpo_student', (array) $user->roles, true)
            || in_array('eleve', (array) $user->roles, true);
    }

    public static function filterClassStudentIds(array $userIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $userIds), static fn($id) => $id > 0)));

        return array_values(array_filter($ids, static fn($id) => self::isClassStudent((int) $id)));
    }

    public static function filterClassStudentRows(array $rows, string $userIdKey = 'user_id'): array
    {
        return array_values(array_filter($rows, static function ($row) use ($userIdKey): bool {
            if (is_array($row)) {
                return self::isClassStudent((int) ($row[$userIdKey] ?? 0));
            }

            if (is_object($row)) {
                return self::isClassStudent((int) ($row->{$userIdKey} ?? 0));
            }

            return false;
        }));
    }

    public static function canBeTrackedByTeacher(int $userId): bool
    {
        return self::isClassStudent($userId);
    }

    public static function canStoreAutonomousProgress(int $userId): bool
    {
        return self::isAutonomousLearner($userId)
            && user_can($userId, Capabilities::TRACK_OWN_PROGRESS);
    }

    public static function isSubjectToSchoolClosure(int $userId): bool
    {
        return $userId > 0 && !self::isAutonomousLearner($userId);
    }
}
