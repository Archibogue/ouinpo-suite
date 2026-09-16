<?php
namespace Ouinpo\Suite\Modules\TicketSimulator;
defined('ABSPATH') || exit;

final class SimulatedTestEngine implements TestEngineInterface
{
    public function __construct(private array $resources = []) {}
    public function run(array $action, array $state): array
    {
        if (!empty($action['code_resource'])) {
            $resource = TicketScenario::index($this->resources)[$action['code_resource']] ?? null;
            $content = $state['code_edits'][$action['code_resource']] ?? ($resource['content'] ?? '');
            $success = $resource && isset($resource['expected_content']) && CodeWorkspace::matches($content, $resource['expected_content']);
            return ['text' => $success ? ($action['success_result'] ?? 'SUCCÈS') : ($action['result'] ?? 'ÉCHEC'), 'outcome' => $success ? 'success' : 'failure'];
        }
        // First matching variant wins. Order is explicit in the scenario editor.
        foreach ($action['variants'] ?? [] as $variant) {
            if (!array_diff($variant['requires'] ?? [], $state['done'])) {
                return ['text' => $variant['result'], 'outcome' => $variant['outcome'] ?? 'info'];
            }
        }
        return ['text' => $action['result'] ?? '', 'outcome' => 'info'];
    }
}
