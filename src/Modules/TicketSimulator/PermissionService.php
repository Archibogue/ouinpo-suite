<?php
namespace Ouinpo\Suite\Modules\TicketSimulator;

use Ouinpo\Suite\Core\Capabilities as Caps;
use Ouinpo\Suite\Core\Privacy\LearningDataPolicy;
defined('ABSPATH') || exit;

final class PermissionService
{
    public static function all(): bool { return Caps::can(Caps::TICKET_ALL); }
    public static function manage(): bool { return self::all() || Caps::can(Caps::TICKET_MANAGE); }
    public static function scenario(array $scenario): bool { return self::all() || (self::manage() && (int) $scenario['owner_id'] === get_current_user_id()); }
    public static function practice(): bool
    {
        return is_user_logged_in() && Caps::can(Caps::TICKET_PRACTICE)
            && (new LearningDataPolicy())->canStoreLearningData(get_current_user_id());
    }
    public static function observe(array $attempt): bool
    {
        return self::all() || (Caps::can(Caps::TICKET_OBSERVE) && (int) $attempt['teacher_id'] === get_current_user_id());
    }
    public static function view(array $attempt): bool
    {
        return self::observe($attempt) || (Caps::can(Caps::TICKET_PRACTICE) && (int) $attempt['student_id'] === get_current_user_id());
    }
    public static function edit(array $attempt): bool
    {
        return self::practice() && (int) $attempt['student_id'] === get_current_user_id() && $attempt['status'] !== 'archived';
    }
    public static function require(bool $allowed): void
    {
        if (!$allowed) { throw new \RuntimeException('Accès refusé.', 403); }
    }
}
