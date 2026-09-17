<?php
namespace Ouinpo\Suite\Modules\TicketSimulator;
defined('ABSPATH') || exit;

/** Editable pedagogical snippets, scoped to a ticket attempt. Never executes content. */
final class CodeWorkspace
{
    public static function save(array $ticket, array $state, array $resource, string $content): array
    {
        if (empty($resource['editable']) || !in_array($resource['id'], $ticket['resources'], true)
            || !in_array($resource['id'], $state['visible_resources'], true)) {
            throw new \RuntimeException('Cette ressource ne peut pas être modifiée.', 403);
        }
        if (!empty($state['pending']) || in_array($state['status'], ['new','resolved','closed'], true)) {
            throw new \DomainException('Reprenez le traitement du ticket avant de modifier le code.');
        }
        if (strlen($content) > 100000 || str_contains($content, "\0")) {
            throw new \InvalidArgumentException('Extrait invalide ou trop volumineux (100 Ko maximum).');
        }
        $state['code_edits'][$resource['id']] = $content;
        $state['awarded_actions'] ??= $state['done'];
        // A successful test of an older draft cannot validate newly changed code.
        foreach ($ticket['actions'] as $action) {
            if (($action['code_resource'] ?? '') === $resource['id']) {
                unset($state['tests'][$action['id']]);
                $state['done'] = array_values(array_diff($state['done'], [$action['id']]));
            }
        }
        unset($state['review']);
        return [$state, [['type'=>'code_edit', 'resource_id'=>$resource['id'],
            'text'=>'Extrait enregistré : ' . ($resource['filename'] ?? $resource['label']), 'content'=>$content]]];
    }

    public static function resource(array $resource, array $state): array
    {
        $data = TicketResource::publicData($resource);
        $data['content'] = $state['code_edits'][$resource['id']] ?? ($resource['content'] ?? '');
        if (!empty($resource['editable'])) { $data['initial_content'] = $resource['content'] ?? ''; }
        return $data;
    }

    public static function matches(string $actual, string $expected): bool
    {
        // Normalize line endings and trailing spaces only; this is not a semantic code runner.
        $normalize = static fn(string $s): string => trim(implode("\n", array_map('rtrim', explode("\n", str_replace(["\r\n", "\r"], "\n", $s)))));
        return $normalize($actual) === $normalize($expected);
    }
}
