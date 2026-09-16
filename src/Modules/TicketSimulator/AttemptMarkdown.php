<?php
namespace Ouinpo\Suite\Modules\TicketSimulator;
defined('ABSPATH') || exit;

/** A student-facing report: never exports the private scenario definition. */
final class AttemptMarkdown
{
    private const STATUS = ['new'=>'Nouveau','accepted'=>'Pris en charge','diagnosing'=>'En diagnostic','waiting_user'=>'En attente utilisateur','waiting_specialist'=>'En attente spécialiste','escalated'=>'Escaladé','resolving'=>'En cours de résolution','resolved'=>'Résolu','closed'=>'Clôturé','reopened'=>'Réouvert'];
    private const EVENTS = ['created'=>'Ticket reçu','action'=>'Action','status'=>'Statut','qualification'=>'Qualification','user_message'=>'Message au demandeur','user_reply'=>'Réponse du demandeur','specialist_request'=>'Demande au spécialiste','specialist_reply'=>'Réponse du spécialiste','technical_note'=>'Note technique','test'=>'Test simulé','result'=>'Résultat','resource'=>'Consultation','code_edit'=>'Code enregistré','resolution'=>'Compte rendu','archived'=>'Archivage','requester_validation'=>'Validation simulée du demandeur'];

    public static function download(int $id): array
    {
        // The attempt lock gives a consistent report while other tabs are writing.
        return ScenarioRepository::transaction(static function () use ($id): array {
            $repo = new AttemptRepository();
            $attempt = $repo->get($id, true);
            PermissionService::require(PermissionService::view($attempt));
            $states = $repo->states($id);
            $events = []; $after = 0;
            do {
                $page = (new EventRepository())->listing($id, $after);
                foreach ($page as $event) { $events[] = $event; $after = (int) $event['id']; }
            } while (count($page) === 500);
            return ['filename'=>'patadesk-tentative-' . ($attempt['number'] ?? $id) . '.md', 'markdown'=>self::render($attempt, $states, $events)];
        });
    }

    public static function render(array $attempt, array $states, array $events): string
    {
        // Even an observing teacher receives the student version of this export.
        $view = StudentView::build($attempt, $states, false);
        $scenario = json_decode($attempt['snapshot'], true);
        $finished = count(array_filter($view['tickets'], static fn($t) => in_array($t['status'], ['resolved','closed'], true)));
        $lines = ['# Bilan de scénario — ' . self::text($view['title']), '',
            '- Tentative : #' . (int) ($attempt['number'] ?? $attempt['id']),
            '- Étudiant : #' . (int) $attempt['student_id'],
            '- Exporté le : ' . gmdate('Y-m-d H:i:s') . ' UTC',
            '- État : ' . self::text(['active'=>'En cours','completed'=>'Terminée','archived'=>'Archivée'][$attempt['status']] ?? $attempt['status']),
            '- Début : ' . self::text($attempt['started_at']) . ' UTC',
            '- Fin : ' . (!empty($attempt['ended_at']) ? self::text($attempt['ended_at']) . ' UTC' : 'Non terminée'),
            '- Tickets résolus ou clôturés : ' . $finished . '/' . count($view['tickets']), '',
            '- Critère de fin : ' . ($view['completion_status'] === 'closed' ? 'clôture de tous les tickets' : 'résolution de tous les tickets'),
            '- Parcours terminé : ' . ($view['path_completed'] ? 'oui' : 'non'),
            '- Appréciation pédagogique : à établir par l’enseignant ; la fin du parcours et les contrôles automatiques ne valident pas la compétence.', '',
            'Les tests sont simulés. La comparaison de code ne constitue pas une exécution réelle.', '',
            'Ce bilan présente le travail enregistré dans la simulation, à la date du téléchargement.', '',
            '## Contexte', '', self::text($view['description']), ''];
        foreach ($view['tickets'] as $ticket) {
            $state = $states[$ticket['id']];
            $lines[] = '## ' . self::text($ticket['id'] . ' — ' . $ticket['title']);
            $lines[] = '';
            $lines[] = '- Demandeur : ' . self::text($ticket['requester']);
            $lines[] = '- Statut : ' . self::text(self::STATUS[$ticket['status']] ?? $ticket['status']);
            $lines[] = '- Actions distinctes : ' . count($ticket['done']);
            $lines[] = '- Temps fictif cumulé : ' . (int) $ticket['minutes'] . ' min';
            $lines[] = '- Contrôles automatiques : ' . ($ticket['automatic_checks'] === null ? 'pas encore évalués' : ($ticket['automatic_checks'] ? 'satisfaits (présence des éléments exigés, pas leur pertinence)' : 'non satisfaits'));
            $lines[] = '- Validation du demandeur avant clôture : ' . ($ticket['requester_validation_required'] ? 'requise par ce scénario' : 'non exigée par ce scénario');
            $lines[] = '- Décision de clôture : ' . ($ticket['status'] === 'closed' ? 'ticket clôturé' : 'ticket non clôturé');
            if ($ticket['requester_validation']) {
                $lines[] = '- Retour simulé : ' . ($ticket['requester_validation']['outcome'] === 'confirmed' ? 'confirmation' : 'problème persistant, réouverture');
                $lines[] = self::block($ticket['requester_validation']['message']);
            }
            $lines[] = '';
            $lines[] = '### Demande initiale';
            $lines[] = '';
            $lines[] = self::text($ticket['description']);
            $lines[] = '';
            $lines[] = '### Possibilités dans PataDesk';
            $lines[] = '';
            $lines[] = 'Simulation à actions prédéfinies : aucun terminal système, accès SQL libre, accès à une machine réelle ou environnement de production. La console permet seulement de modifier les extraits pédagogiques autorisés ; les tests sont simulés. Les échanges et leur historique sont enregistrés automatiquement.';
            $lines[] = '';
            $readOnly = $attempt['status'] === 'archived' || $ticket['status'] === 'closed';
            $lines[] = $readOnly
                ? 'Ce ticket est terminé ou archivé : les conseils portent sur une prochaine tentative, pas sur des modifications à effectuer maintenant.'
                : ($ticket['status'] === 'resolved'
                    ? 'Ticket résolu : la qualification et le code ne sont plus modifiables. Utiliser uniquement les actions proposées pour la validation ou la clôture ; les notes restent accessibles.'
                    : 'Outils généraux : qualifier le ticket, enregistrer une note technique et renseigner le compte rendu de résolution lorsque les conditions du scénario sont remplies.');
            $lines[] = '';
            $lines[] = 'Actions proposées maintenant (les autres actions futures ne sont pas connues) :';
            if ($readOnly || !$ticket['actions']) { $lines[] = '- Aucune.'; }
            else {
                foreach ($ticket['actions'] as $action) { $lines[] = '- ' . self::text($action['label']); }
            }
            $lines[] = '';
            $lines[] = 'Actions déjà réalisées dans ce scénario :';
            $definition = TicketScenario::index($scenario['tickets'])[$ticket['id']];
            $completed = false;
            foreach ($definition['actions'] as $action) {
                if (!in_array($action['id'], $ticket['done'], true)) { continue; }
                $completed = true;
                $lines[] = '- ' . self::text($action['label']);
            }
            if (!$completed) { $lines[] = '- Aucune.'; }
            $lines[] = '';
            $lines[] = 'Ressources révélées (la modification exige aussi un ticket actif, pris en charge et sans réponse en attente) :';
            foreach ($scenario['resources'] as $resource) {
                if (!in_array($resource['id'], $state['visible_resources'], true)) { continue; }
                $lines[] = '- ' . self::text($resource['label']) . (!empty($resource['editable']) ? ' — extrait pédagogique modifiable.' : ' — consultation uniquement.');
            }
            $lines[] = '';
            $lines[] = '### Qualification enregistrée';
            $lines[] = '';
            $labels = ['nature'=>'Nature de la demande','priority_justification'=>'Justification de priorité','category'=>'Catégorie technique','subcategory'=>'Sous-catégorie','priority'=>'Priorité','impact'=>'Impact','urgency'=>'Urgence','assignee'=>'Assignation','service'=>'Service','it_service'=>'Service informatique','application'=>'Application','location'=>'Localisation','sla'=>'SLA fictif','fictional_date'=>'Date fictive'];
            foreach ($ticket['fields'] as $key => $value) {
                if ($key === 'nature') { $value = ['incident'=>'Incident','service'=>'Assistance / service','evolution'=>'Évolution'][$value] ?? $value; }
                $lines[] = '- ' . self::text($labels[$key] ?? $key) . ' : ' . self::text($value);
            }
            $lines[] = '';
            $lines[] = '### Démarche et échanges';
            $lines[] = '';
            $hasEvents = false;
            foreach ($events as $event) {
                if ($event['ticket_key'] !== $ticket['id'] || !isset(self::EVENTS[$event['event_type']])) { continue; }
                $hasEvents = true;
                $lines[] = '**' . self::text($event['created_at'] . ' UTC — ' . self::EVENTS[$event['event_type']]) . '**';
                $lines[] = '';
                $lines[] = self::block((string) ($event['payload']['text'] ?? ''));
                $lines[] = '';
            }
            if (!$hasEvents) { $lines[] = 'Aucune action enregistrée.'; $lines[] = ''; }
            $lines[] = '### Derniers résultats des tests';
            $lines[] = '';
            if (!$ticket['tests']) { $lines[] = 'Aucun résultat actuel. Les tests liés à un extrait modifié doivent être relancés.'; }
            foreach ($ticket['tests'] as $testId => $test) {
                $lines[] = '- ' . self::text($testId) . ' : ' . self::text(['success'=>'Succès','failure'=>'Échec','info'=>'Information'][$test['outcome']] ?? $test['outcome']);
                $lines[] = ''; $lines[] = self::block($test['text']); $lines[] = '';
            }
            foreach ($scenario['resources'] as $resource) {
                if (!in_array($resource['id'], $state['visible_resources'], true) || !isset($state['code_edits'][$resource['id']])) { continue; }
                $lines[] = ''; $lines[] = '### Mon dernier code enregistré — ' . self::text($resource['filename'] ?? $resource['label']);
                $lines[] = ''; $lines[] = self::block($state['code_edits'][$resource['id']]); $lines[] = '';
            }
            $lines[] = ''; $lines[] = '### Compte rendu de résolution'; $lines[] = '';
            if (!$ticket['resolution']) { $lines[] = 'Aucune résolution renseignée.'; }
            else {
                foreach (['cause'=>'Cause ou analyse de la demande','solution'=>'Solution / actions réalisées','tests'=>'Tests effectués','result'=>'Résultat','message'=>'Message final au demandeur'] as $key => $label) {
                    $lines[] = '**' . $label . '**'; $lines[] = ''; $lines[] = self::block((string) ($ticket['resolution'][$key] ?? '')); $lines[] = '';
                }
            }
            $lines[] = '';
        }
        return implode("\n", $lines) . "\n";
    }

    private static function text(string $value): string
    {
        $value = htmlspecialchars($value, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return strtr($value, ['\\'=>'\\\\','`'=>'\\`','*'=>'\\*','_'=>'\\_','['=>'\\[',']'=>'\\]','#'=>'\\#','|'=>'\\|']);
    }
    private static function block(string $value): string
    {
        preg_match_all('/`+/', $value, $runs);
        $length = 3;
        foreach ($runs[0] as $run) { $length = max($length, strlen($run) + 1); }
        $fence = str_repeat('`', $length);
        return $fence . "text\n" . $value . "\n" . $fence;
    }
}
