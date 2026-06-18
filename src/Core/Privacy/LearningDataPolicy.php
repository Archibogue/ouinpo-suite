<?php

namespace Ouinpo\Suite\Core\Privacy;

use Ouinpo\Suite\Core\Capabilities;

defined('ABSPATH') || exit;

final class LearningDataPolicy
{
    public function isAlumni(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $user = get_user_by('id', $userId);
        if (!$user) {
            return false;
        }

        return in_array('ouinpo_alumni', (array) $user->roles, true);
    }

    public function isTrackingDisabled(int $userId): bool
    {
        if ($userId <= 0) {
            return true;
        }

        return $this->isAlumni($userId)
            || (string) get_user_meta($userId, 'ouinpo_tracking_disabled', true) === '1'
            || (
                !user_can($userId, Capabilities::TRACK_LEARNING_DATA)
                && !LearningAudiencePolicy::canStoreAutonomousProgress($userId)
            );
    }

    public function canPracticeExercises(int $userId): bool
    {
        return $userId > 0 && user_can($userId, Capabilities::PRACTICE_EXERCISES);
    }

    public function canStoreLearningData(int $userId): bool
    {
        return $userId > 0 && !$this->isTrackingDisabled($userId);
    }

    public function canUseStudentDashboard(int $userId): bool
    {
        return $this->canStoreLearningData($userId)
            && (
                user_can($userId, Capabilities::VIEW_OWN_LEARNING_DATA)
                || user_can($userId, Capabilities::VIEW_OWN_PROGRESS)
            );
    }

    public function canBeAssignedToClass(int $userId): bool
    {
        return $userId > 0
            && !$this->isAlumni($userId)
            && !LearningAudiencePolicy::isAutonomousLearner($userId);
    }

    public function disableTrackingForAlumni(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        update_user_meta($userId, 'ouinpo_tracking_disabled', '1');
        update_user_meta($userId, 'ouinpo_alumni_since', current_time('mysql'));
    }

    public static function trackingDisabledResponse(): array
    {
        return [
            'ok' => true,
            'stored' => false,
            'reason' => 'tracking_disabled',
        ];
    }
}
