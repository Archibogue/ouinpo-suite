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
    public const UPLOAD_SUBMISSION = 'ouinpo_upload_submission';
    public const SUBMIT_WORK = 'ouinpo_submit_work';
    public const PRACTICE_EXERCISES = 'ouinpo_practice_exercises';
    public const TRACK_LEARNING_DATA = 'ouinpo_track_learning_data';
    public const VIEW_OWN_LEARNING_DATA = 'ouinpo_view_own_learning_data';
    public const VIEW_REVISIONS = 'ouinpo_view_revisions';
    public const VIEW_WRITTEN_SUBJECTS = 'ouinpo_view_written_subjects';
    public const VIEW_PRACTICAL_SUBJECTS = 'ouinpo_view_practical_subjects';
    public const TRACK_OWN_PROGRESS = 'ouinpo_track_own_progress';
    public const VIEW_OWN_PROGRESS = 'ouinpo_view_own_progress';
    public const EARN_BADGES = 'ouinpo_earn_badges';
    public const VIEW_PUBLIC_PATHS = 'ouinpo_view_public_paths';
    public const START_PUBLIC_PATHS = 'ouinpo_start_public_paths';
    public const PORTFOLIO_VIEW_OWN_ARCHIVE = 'ouinpo_portfolio_view_own_archive';
    public const PORTFOLIO_EXPORT_OWN = 'ouinpo_portfolio_export_own';
    public const PROJECTS_MANAGE_ALL = 'ouinpo_projects_manage_all';
    public const PROJECTS_MANAGE_CLASS = 'ouinpo_projects_manage_class';
    public const PROJECTS_CREATE = 'ouinpo_projects_create';
    public const PROJECTS_VIEW_OWN = 'ouinpo_projects_view_own';
    public const PROJECTS_EDIT_OWN_TASKS = 'ouinpo_projects_edit_own_tasks';
    public const PROJECTS_COMMENT = 'ouinpo_projects_comment';
    public const PROJECTS_VALIDATE = 'ouinpo_projects_validate';
    public const PROJECTS_AI_USE = 'ouinpo_projects_ai_use';
    public const PROJECTS_AI_APPLY = 'ouinpo_projects_ai_apply';
    public const PROJECTS_AI_STUDENT_USE = 'ouinpo_projects_ai_student_use';

    private static bool $initialized = false;

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        add_filter('user_has_cap', [self::class, 'grantManageOptionsCompatibility'], 10, 4);
        add_filter('option_page_capability_ouinpo_suite_settings', [self::class, 'settingsOptionCapability']);
        add_filter('option_page_capability_ouinpo_meta_social_group', [self::class, 'settingsOptionCapability']);
        add_filter('option_page_capability_ouinpo_sf', [self::class, 'aiOptionCapability']);
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
            self::PRACTICE_EXERCISES,
            self::TRACK_LEARNING_DATA,
            self::VIEW_OWN_LEARNING_DATA,
            self::VIEW_REVISIONS,
            self::VIEW_WRITTEN_SUBJECTS,
            self::VIEW_PRACTICAL_SUBJECTS,
            self::TRACK_OWN_PROGRESS,
            self::VIEW_OWN_PROGRESS,
            self::EARN_BADGES,
            self::VIEW_PUBLIC_PATHS,
            self::START_PUBLIC_PATHS,
            self::PORTFOLIO_VIEW_OWN_ARCHIVE,
            self::PORTFOLIO_EXPORT_OWN,
            self::PROJECTS_MANAGE_ALL,
            self::PROJECTS_MANAGE_CLASS,
            self::PROJECTS_CREATE,
            self::PROJECTS_VIEW_OWN,
            self::PROJECTS_EDIT_OWN_TASKS,
            self::PROJECTS_COMMENT,
            self::PROJECTS_VALIDATE,
            self::PROJECTS_AI_USE,
            self::PROJECTS_AI_APPLY,
            self::PROJECTS_AI_STUDENT_USE,
        ];
    }

    public static function student(): array
    {
        return [
            'read',
            self::PRACTICE_EXERCISES,
            self::TRACK_LEARNING_DATA,
            self::VIEW_OWN_LEARNING_DATA,
            self::UPLOAD_SUBMISSION,
            self::SUBMIT_WORK,
            self::PROJECTS_VIEW_OWN,
            self::PROJECTS_EDIT_OWN_TASKS,
            self::PROJECTS_COMMENT,
            self::PROJECTS_AI_STUDENT_USE,
        ];
    }

    public static function alumni(): array
    {
        return [
            'read',
            self::PRACTICE_EXERCISES,
            self::PORTFOLIO_VIEW_OWN_ARCHIVE,
            self::PORTFOLIO_EXPORT_OWN,
        ];
    }

    public static function learner(): array
    {
        return [
            'read',
            self::PRACTICE_EXERCISES,
            self::VIEW_REVISIONS,
            self::VIEW_WRITTEN_SUBJECTS,
            self::VIEW_PRACTICAL_SUBJECTS,
            self::TRACK_OWN_PROGRESS,
            self::VIEW_OWN_PROGRESS,
            self::EARN_BADGES,
            self::VIEW_PUBLIC_PATHS,
            self::START_PUBLIC_PATHS,
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
            self::UPLOAD_SUBMISSION => 'Envoyer un fichier de depot OuInPo',
            self::SUBMIT_WORK => 'Soumettre un travail OuInPo',
            self::PRACTICE_EXERCISES => 'Pratiquer les exercices',
            self::TRACK_LEARNING_DATA => 'Stocker le suivi pedagogique',
            self::VIEW_OWN_LEARNING_DATA => 'Voir ses donnees pedagogiques',
            self::VIEW_REVISIONS => 'Voir les revisions',
            self::VIEW_WRITTEN_SUBJECTS => 'Voir les sujets ecrits',
            self::VIEW_PRACTICAL_SUBJECTS => 'Voir les sujets pratiques',
            self::TRACK_OWN_PROGRESS => 'Stocker sa progression personnelle',
            self::VIEW_OWN_PROGRESS => 'Voir sa progression personnelle',
            self::EARN_BADGES => 'Obtenir des badges',
            self::VIEW_PUBLIC_PATHS => 'Voir les parcours publics',
            self::START_PUBLIC_PATHS => 'Demarrer les parcours publics',
            self::PORTFOLIO_VIEW_OWN_ARCHIVE => 'Portfolio - voir ses archives',
            self::PORTFOLIO_EXPORT_OWN => 'Portfolio - exporter ses archives',
            self::PROJECTS_MANAGE_ALL => 'Projets - gestion globale',
            self::PROJECTS_MANAGE_CLASS => 'Projets - gestion de classe',
            self::PROJECTS_CREATE => 'Projets - creation',
            self::PROJECTS_VIEW_OWN => 'Projets - voir ses projets',
            self::PROJECTS_EDIT_OWN_TASKS => 'Projets - modifier ses taches',
            self::PROJECTS_COMMENT => 'Projets - commenter et journal',
            self::PROJECTS_VALIDATE => 'Projets - validation',
            self::PROJECTS_AI_USE => 'Projets - assistant IA',
            self::PROJECTS_AI_APPLY => 'Projets - appliquer suggestions IA',
            self::PROJECTS_AI_STUDENT_USE => 'Projets - assistant IA eleve',
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
            self::PROJECTS_MANAGE_ALL,
            self::PROJECTS_MANAGE_CLASS,
            self::PROJECTS_CREATE,
            self::PROJECTS_VIEW_OWN,
            self::PROJECTS_EDIT_OWN_TASKS,
            self::PROJECTS_COMMENT,
            self::PROJECTS_VALIDATE,
            self::PROJECTS_AI_USE,
            self::PROJECTS_AI_APPLY,
            self::PROJECTS_AI_STUDENT_USE,
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

        $legacyTeacher = get_role('prof');
        if ($legacyTeacher) {
            foreach ($teacherCaps as $capability) {
                $legacyTeacher->add_cap($capability);
            }
        }

        $studentCaps = self::student();

        $student = get_role('ouinpo_student');
        if (!$student) {
            add_role('ouinpo_student', 'Élève OuInPo', array_fill_keys($studentCaps, true));
            $student = get_role('ouinpo_student');
        }

        if ($student) {
            $student->remove_cap('upload_files');
            foreach ($studentCaps as $capability) {
                $student->add_cap($capability);
            }
        }

        $legacyStudent = get_role('eleve');
        if ($legacyStudent) {
            $legacyStudent->remove_cap('upload_files');
            foreach ($studentCaps as $capability) {
                $legacyStudent->add_cap($capability);
            }
        }

        $alumniCaps = self::alumni();
        $alumni = get_role('ouinpo_alumni');
        if (!$alumni) {
            add_role('ouinpo_alumni', 'Ancien eleve OuInPo', array_fill_keys($alumniCaps, true));
            $alumni = get_role('ouinpo_alumni');
        }

        if ($alumni) {
            foreach ($alumniCaps as $capability) {
                $alumni->add_cap($capability);
            }

            foreach ([
                self::TRACK_LEARNING_DATA,
                self::PROJECTS_EDIT_OWN_TASKS,
                self::PROJECTS_COMMENT,
                self::SUBMIT_WORK,
                self::UPLOAD_SUBMISSION,
                self::PROJECTS_AI_STUDENT_USE,
            ] as $capability) {
                $alumni->remove_cap($capability);
            }
        }

        $learnerCaps = self::learner();
        $learner = get_role('ouinpo_learner');
        if (!$learner) {
            add_role('ouinpo_learner', 'Apprenant autonome NSI', array_fill_keys($learnerCaps, true));
            $learner = get_role('ouinpo_learner');
        }

        if ($learner) {
            foreach ($learnerCaps as $capability) {
                $learner->add_cap($capability);
            }

            foreach ([
                self::MANAGE_CLASSES,
                self::VIEW_STUDENT_DATA,
                self::MANAGE_SUBMISSIONS,
                self::UPLOAD_SUBMISSION,
                self::SUBMIT_WORK,
                self::PROJECTS_MANAGE_ALL,
                self::PROJECTS_MANAGE_CLASS,
                self::PROJECTS_CREATE,
                self::PROJECTS_VIEW_OWN,
                self::PROJECTS_EDIT_OWN_TASKS,
                self::PROJECTS_COMMENT,
                self::PROJECTS_VALIDATE,
                self::PROJECTS_AI_STUDENT_USE,
                self::PORTFOLIO_VIEW_OWN_ARCHIVE,
                self::PORTFOLIO_EXPORT_OWN,
            ] as $capability) {
                $learner->remove_cap($capability);
            }
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

        if ($requested === self::MANAGE_SUITE && empty($allcaps[self::MANAGE_SUITE])) {
            foreach (self::all() as $capability) {
                if ($capability !== self::MANAGE_SUITE && !empty($allcaps[$capability])) {
                    $allcaps[self::MANAGE_SUITE] = true;
                    break;
                }
            }
        }

        return $allcaps;
    }

    public static function settingsOptionCapability(): string
    {
        return self::MANAGE_SETTINGS;
    }

    public static function aiOptionCapability(): string
    {
        return self::MANAGE_AI;
    }
}
