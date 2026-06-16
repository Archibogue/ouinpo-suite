<?php

namespace Ouinpo\Suite\Core\School;

defined('ABSPATH') || exit;

final class CycleTransitionResolver
{
    public function __construct(private readonly ?CycleRepository $repository = null)
    {
    }

    public function resolve(int $fromLevelId, ?int $toLevelId = null, ?array $configuredTransition = null): array
    {
        $repository = $this->repository ?: new CycleRepository();
        $from = $fromLevelId > 0 ? $repository->getLevel($fromLevelId) : null;
        $to = $toLevelId && $toLevelId > 0 ? $repository->getLevel($toLevelId) : null;

        $fromCycleId = $from ? (int) ($from['cycle_id'] ?? 0) : 0;
        $toCycleId = $to ? (int) ($to['cycle_id'] ?? 0) : 0;
        $sameCycle = $fromCycleId > 0 && $fromCycleId === $toCycleId;
        $toLevelNull = !$to || $toLevelId === null || $toLevelId <= 0;
        $exits = $toLevelNull || ($fromCycleId > 0 && $fromCycleId !== $toCycleId);
        $enters = $toCycleId > 0 && $fromCycleId !== $toCycleId;
        $redoublement = $fromLevelId > 0 && $toLevelId !== null && $fromLevelId === $toLevelId && $sameCycle;

        $preserve = null;
        if (is_array($configuredTransition) && array_key_exists('preserve_cycle_data', $configuredTransition)) {
            $preserve = $configuredTransition['preserve_cycle_data'];
            $preserve = $preserve === null ? null : ((int) $preserve === 1);
        }
        if ($preserve === null) {
            $preserve = $sameCycle;
        }

        return [
            'from_level_id' => $fromLevelId,
            'to_level_id' => $toLevelId,
            'from_cycle_id' => $fromCycleId > 0 ? $fromCycleId : null,
            'to_cycle_id' => $toCycleId > 0 ? $toCycleId : null,
            'stays_in_same_cycle' => $sameCycle,
            'exits_cycle' => $exits,
            'enters_new_cycle' => $enters,
            'is_redoublement' => $redoublement,
            'preserve_cycle_data' => $preserve,
            'transition_type' => (string) ($configuredTransition['transition_type'] ?? ($redoublement ? 'redoublement' : ($exits ? 'cycle_exit' : 'promotion'))),
        ];
    }

    public function resolveDefaultNextLevel(int $fromLevelId): array
    {
        $repository = $this->repository ?: new CycleRepository();
        $configured = $repository->defaultTransitionForLevel($fromLevelId);
        if ($configured) {
            return $this->resolve($fromLevelId, !empty($configured['to_level_id']) ? (int) $configured['to_level_id'] : null, $configured);
        }

        $level = $repository->getLevel($fromLevelId);
        $toLevelId = null;
        if ($level && empty($level['is_cycle_terminal']) && !empty($level['default_next_level_id'])) {
            $toLevelId = (int) $level['default_next_level_id'];
        }

        return $this->resolve($fromLevelId, $toLevelId, null);
    }
}
