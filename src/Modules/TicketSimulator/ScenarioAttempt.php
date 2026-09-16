<?php
namespace Ouinpo\Suite\Modules\TicketSimulator;
defined('ABSPATH') || exit;

final class ScenarioAttempt
{
    public static function initial(array $ticket): array
    {
        return ['status' => 'new', 'fields' => $ticket['fields'], 'done' => [],
            'visible_resources' => $ticket['visible_resources'] ?? [], 'pending' => null,
            'minutes' => 0, 'score' => 0, 'tests' => [], 'resolution' => null];
    }
}
