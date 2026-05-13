<?php

namespace Ouinpo\Suite\Core;

defined('ABSPATH') || exit;

final class Capabilities
{
    public const MANAGE_SUITE = 'ouinpo_manage_suite';
    public const MANAGE_SETTINGS = 'ouinpo_manage_settings';
    public const MANAGE_EXERCISES = 'ouinpo_manage_exercises';
    public const MANAGE_PRACTICAL_SUBJECTS = 'ouinpo_manage_practical_subjects';
    public const MANAGE_ASSESSMENTS = 'ouinpo_manage_assessments';
    public const MANAGE_CLASSES = 'ouinpo_manage_classes';
    public const MANAGE_COMPETENCIES = 'ouinpo_manage_competencies';
    public const MANAGE_BADGES = 'ouinpo_manage_badges';
    public const MANAGE_AI = 'ouinpo_manage_ai';
    public const VIEW_STUDENT_DATA = 'ouinpo_view_student_data';
    public const MANAGE_SUBMISSIONS = 'ouinpo_manage_submissions';

    private static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        add_filter('user_has_cap', [self::class, 'grantManageOptionsCompatibility'], 10, 4);
    }

    public static function all(): array
    {
        return [
            self::MANAGE_SUITE,
            self::MANAGE_SETTINGS,
            self::MANAGE_EXERCISES,
            self::MANAGE_PRACTICAL_SUBJECTS,
            self::MANAGE_ASSESSMENTS,
            self::MANAGE_CLASSES,
            self::MANAGE_COMPETENCIES,
            self::MANAGE_BADGES,
            self::MANAGE_AI,
            self::VIEW_STUDENT_DATA,
            self::MANAGE_SUBMISSIONS,
        ];
    }

    public static function labels(): array
    {
        return [
            self::MANAGE_SUITE => 'Accès OuInPo Suite',
            self::MANAGE_SETTINGS => 'Réglages généraux',
            self::MANAGE_EXERCISES => 'Exercices',
            self::MANAGE_PRACTICAL_SUBJECTS => 'Sujets pratiques',
            self::MANAGE_ASSESSMENTS => 'Devoirs et évaluations',
            self::MANAGE_CLASSES => 'Classes et élèves',
            self::MANAGE_COMPETENCIES => 'Référentiel et compétences',
            self::MANAGE_BADGES => 'Badges',
            self::MANAGE_AI => 'IA et parcours',
            self::VIEW_STUDENT_DATA => 'Consultation des données élèves',
            self::MANAGE_SUBMISSIONS => 'Dépôts élèves',
        ];
    }

    public static function install(): void
    {
        $administrator = get_role('administrator');
        if ($administrator) {
            foreach (self::all() as $capability) {
                $administrator->add_cap($capability);
            }
        }

        $teacherCaps = [
            'read',
            self::MANAGE_SUITE,
            self::MANAGE_EXERCISES,
            self::MANAGE_PRACTICAL_SUBJECTS,
            self::MANAGE_ASSESSMENTS,
            self::MANAGE_CLASSES,
            self::MANAGE_COMPETENCIES,
            self::MANAGE_BADGES,
            self::VIEW_STUDENT_DATA,
            self::MANAGE_SUBMISSIONS,
        ];

        $teacher = get_role('ouinpo_teacher');
        if (!$teacher) {
            add_role('ouinpo_teacher', 'Enseignant OuInPo', array_fill_keys($teacherCaps, true));
            $teacher = get_role('ouinpo_teacher');
        }

        if ($teacher) {
            foreach ($teacherCaps as $capability) {
                $teacher->add_cap($capability);
            }
        }

        if (!get_role('ouinpo_student')) {
            add_role('ouinpo_student', 'Élève OuInPo', ['read' => true]);
        }
    }

    public static function can(string $capability): bool
    {
        return current_user_can($capability) || current_user_can('manage_options');
    }

    public static function grantManageOptionsCompatibility(array $allcaps, array $caps, array $args, $user): array
    {
        $requested = isset($args[0]) && is_string($args[0]) ? $args[0] : '';

        if ($requested !== '' && in_array($requested, self::all(), true) && !empty($allcaps['manage_options'])) {
            $allcaps[$requested] = true;
        }

        return $allcaps;
    }
}
