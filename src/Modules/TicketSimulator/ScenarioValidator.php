<?php
namespace Ouinpo\Suite\Modules\TicketSimulator;
defined('ABSPATH') || exit;

/** Strict declarative format. No expressions, executable callbacks or external paths. */
final class ScenarioValidator
{
    public static function validate(array $s): array
    {
        self::need(($s['format_version'] ?? null) === 1, 'Version de format attendue : 1.');
        self::string($s, 'title', true, 200);
        self::string($s, 'description');
        self::need(in_array($s['completion_status'] ?? 'resolved', ['qualified','oriented','resolved','closed'], true), 'Objectif de parcours inconnu.');
        self::string($s, 'priority_policy'); self::string($s, 'service_agreement');
        foreach (['users','specialists','resources','tickets'] as $key) {
            self::need(isset($s[$key]) && is_array($s[$key]) && array_is_list($s[$key]) && count($s[$key]) <= 100, "Liste invalide : $key.");
            self::unique($s[$key]);
        }
        self::need(count($s['tickets']) > 0, 'Ajoutez au moins un ticket.');
        foreach (array_merge($s['users'], $s['specialists']) as $person) { self::string($person, 'label', true); self::string($person, 'service'); }
        $resources = TicketScenario::index($s['resources']);
        foreach ($resources as $r) {
            self::string($r, 'label', true); self::string($r, 'content', false, 100000);
            self::string($r, 'filename'); self::string($r, 'language');
            self::need(!isset($r['editable']) || is_bool($r['editable']), 'Option de modification invalide.');
            self::string($r, 'expected_content', false, 100000);
            self::need(in_array($r['type'] ?? '', TicketResource::TYPES, true), 'Type de ressource inconnu.');
        }
        $people = TicketScenario::index($s['users']);
        $specialists = TicketScenario::index($s['specialists']);
        foreach ($s['tickets'] as $t) {
            self::need(!isset($t['optional']) || is_bool($t['optional']), 'Option de ticket invalide.');
            self::refs($t['trace_required'] ?? [], array_fill_keys(Pedagogy::TRACES,true));
            self::string($t, 'title', true); self::string($t, 'description', true);
            self::need(in_array($t['intake_mode'] ?? 'prepared', ['prepared','from_request'], true), 'Mode de création du ticket invalide.');
            self::string($t, 'raw_request', TicketIntake::enabled($t));
            if (isset($t['ai_dialogue'])) {
                $dialogue = $t['ai_dialogue'];
                self::need(is_array($dialogue) && is_bool($dialogue['enabled'] ?? null), 'Configuration des échanges IA invalide.');
                self::string($dialogue, 'requester_context', false, 6000);
                self::need(is_array($dialogue['specialists'] ?? []) && array_is_list($dialogue['specialists'] ?? []) && count($dialogue['specialists'] ?? []) <= 20, 'Liste des spécialistes IA invalide.');
                $seen = [];
                foreach ($dialogue['specialists'] ?? [] as $contact) {
                    self::need(is_array($contact) && is_string($contact['specialist_id'] ?? null) && isset($specialists[$contact['specialist_id']]) && !isset($seen[$contact['specialist_id']]), 'Spécialiste IA inconnu ou dupliqué.');
                    $seen[$contact['specialist_id']] = true;
                    self::string($contact, 'context', true, 6000);
                }
                self::need(!$dialogue['enabled'] || trim($dialogue['requester_context'] ?? '') !== '' || count($seen) > 0, 'Renseignez les faits connus d’au moins un interlocuteur IA.');
            }
            self::need(isset($people[$t['requester_id'] ?? '']), 'Demandeur introuvable.');
            self::need(!isset($t['guided']) || is_bool($t['guided']), 'Mode guidé : booléen attendu.');
            self::refs($t['qualification_required'] ?? [], array_fill_keys(array_merge(Pedagogy::QUALIFICATION,['category']), true));
            if (isset($t['requester_validation'])) {
                $validation = $t['requester_validation'];
                self::need(is_array($validation) && is_bool($validation['enabled'] ?? null), 'Validation du demandeur invalide.');
                if ($validation['enabled']) {
                    self::need(is_array($validation['replies'] ?? null) && array_is_list($validation['replies']) && count($validation['replies']) >= 1 && count($validation['replies']) <= 10, 'Prévoir de 1 à 10 réponses du demandeur.');
                    foreach ($validation['replies'] as $reply) {
                        self::need(is_array($reply) && in_array($reply['outcome'] ?? '', ['confirmed','persists'], true), 'Réponse : confirmed ou persists attendu.');
                        self::string($reply, 'message', true);
                    }
                    self::need(end($validation['replies'])['outcome'] === 'confirmed', 'La dernière réponse doit permettre la confirmation.');
                    self::need(in_array('closed', $t['transitions']['resolved'] ?? [], true), 'Autorisez la clôture depuis resolved.');
                    if (in_array('persists', array_column($validation['replies'], 'outcome'), true)) {
                        self::need(in_array('reopened', $t['transitions']['resolved'] ?? [], true), 'Autorisez la réouverture depuis resolved.');
                        self::need(in_array('resolved', $t['transitions']['reopened'] ?? [], true), 'Autorisez une nouvelle résolution depuis reopened.');
                    }
                }
            }
            foreach (['fields','expected'] as $key) {
                self::need(isset($t[$key]) && is_array($t[$key]), "Objet requis : $key.");
                foreach ($t[$key] as $field => $value) {
                    self::need(in_array($field, TicketScenario::FIELDS, true) && is_string($value) && strlen($value) <= 1000, 'Champ ticket invalide.');
                    if ($field === 'nature') { self::need(in_array($value, array_merge(['','À qualifier'], Pedagogy::NATURES), true), 'Nature attendue : incident, service ou evolution.'); }
                }
            }
            self::refs($t['resources'] ?? [], $resources);
            self::refs($t['visible_resources'] ?? [], array_fill_keys($t['resources'] ?? [], true));
            self::need(isset($t['actions']) && is_array($t['actions']) && array_is_list($t['actions']) && count($t['actions']) <= 200, 'Actions invalides.');
            self::unique($t['actions']);
            $actions = TicketScenario::index($t['actions']);
            self::need(isset($t['transitions']) && is_array($t['transitions']), 'Transitions requises.');
            foreach ($t['transitions'] as $from => $to) {
                self::need(in_array($from, TicketScenario::STATUSES, true), 'État source inconnu.');
                self::refs($to, array_fill_keys(TicketScenario::STATUSES, true));
            }
            foreach ($actions as $a) {
                self::need(!in_array($a['id'], ['__reply','__validate_requester'], true), 'Identifiant réservé au moteur.');
                if (!empty($t['guided']) || Pedagogy::validation($t)) {
                    self::need(($a['type'] ?? '') === 'resolve' || !in_array($a['to_status'] ?? '', ['resolved'], true), 'Seule une action resolve peut résoudre le ticket en mode guidé.');
                    self::need(!in_array($a['return_status'] ?? '', ['resolved','closed'], true), 'Une réponse ne peut pas remplacer la résolution ou la clôture.');
                    self::need(($a['type'] ?? '') === 'close' || ($a['to_status'] ?? '') !== 'closed', 'Seule une action close peut clôturer le ticket.');
                }
                self::string($a, 'label', true); self::string($a, 'description'); self::string($a, 'result');
                self::string($a, 'success_result');
                if (!empty($a['code_resource'])) {
                    self::need(is_string($a['code_resource']) && in_array($a['code_resource'], $t['resources'], true), 'Ressource du test non associée au ticket.');
                    $resource = $resources[$a['code_resource']];
                    self::need(($a['type'] ?? '') === 'test' && !empty($resource['editable']) && isset($resource['expected_content']) && trim($resource['expected_content']) !== '', 'Un test de code nécessite une ressource modifiable et sa correction attendue.');
                }
                self::need(in_array($a['type'] ?? '', TicketScenario::ACTION_TYPES, true), 'Type d’action inconnu.');
                self::need(!isset($a['hint']) || is_bool($a['hint']), 'Option aide invalide.');
                self::refs($a['requires'] ?? [], $actions);
                self::refs($a['states'] ?? [], array_fill_keys(TicketScenario::STATUSES, true));
                self::refs($a['reveal'] ?? [], array_fill_keys($t['resources'] ?? [], true));
                self::need(!in_array($a['id'], $a['requires'] ?? [], true), 'Une action ne peut pas dépendre d’elle-même.');
                if (!empty($a['specialist_id'])) { self::need(isset($specialists[$a['specialist_id']]), 'Spécialiste inconnu.'); }
                foreach (['to_status','return_status'] as $key) {
                    if (!empty($a[$key])) { self::need(in_array($a[$key], TicketScenario::STATUSES, true), 'État cible inconnu.'); }
                }
                if (in_array($a['type'], ['question','specialist','transfer','escalate','reassign'], true)) {
                    $defaults = ['question'=>'waiting_user','specialist'=>'waiting_specialist','transfer'=>'waiting_specialist','escalate'=>'escalated','reassign'=>'escalated'];
                    $waiting = !empty($a['to_status']) ? $a['to_status'] : $defaults[$a['type']];
                    $return = !empty($a['return_status']) ? $a['return_status'] : ($a['type'] === 'reassign' ? $waiting : 'diagnosing');
                    self::need($return === $waiting || in_array($return, $t['transitions'][$waiting] ?? [], true),
                        'Ticket ' . $t['id'] . ', action « ' . $a['label'] . ' » : retour de réponse non autorisé (' . $waiting . ' → ' . $return . '). Corrigez l’état de retour ou les transitions autorisées du ticket.');
                }
                foreach (['cost','score'] as $key) { self::need(!isset($a[$key]) || (is_int($a[$key]) && abs($a[$key]) <= 10000 && ($key !== 'cost' || $a[$key] >= 0)), 'Coût ou score invalide.'); }
                foreach (['repeatable','requires_message'] as $key) { self::need(!isset($a[$key]) || is_bool($a[$key]), 'Booléen attendu.'); }
                self::need(!isset($a['variants']) || (is_array($a['variants']) && array_is_list($a['variants']) && count($a['variants']) <= 50), 'Variantes invalides.');
                foreach ($a['variants'] ?? [] as $v) {
                    self::need(is_array($v), 'Variante invalide.');
                    self::refs($v['requires'] ?? [], $actions); self::string($v, 'result', true);
                    self::need(in_array($v['outcome'] ?? 'info', ['info','success','failure'], true), 'Résultat de test invalide.');
                }
                if (in_array($a['type'], ['specialist','transfer','escalate','reassign'], true)) {
                    self::need(!empty($a['specialist_id']), 'Choisissez le spécialiste destinataire.');
                }
            }
            // Reject circular prerequisites; a cyclic graph creates unreachable actions.
            $done = [];
            for ($i = 0; $i <= count($actions); $i++) {
                foreach ($actions as $a) { if (!array_diff($a['requires'] ?? [], $done)) { $done[] = $a['id']; } }
                $done = array_values(array_unique($done));
            }
            self::need(count($done) === count($actions), 'Les prérequis contiennent un cycle.');
            self::refs($t['resolution_requires'] ?? [], $actions);
            self::refs($t['resolution_tests'] ?? [], $actions);
            foreach ($t['resolution_tests'] ?? [] as $testId) { self::need($actions[$testId]['type'] === 'test', 'Un test est attendu pour la validation de résolution.'); }
            foreach ($t['resolution_requires'] ?? [] as $id) { self::need(!in_array($actions[$id]['type'], ['resolve','close'], true), 'Attendu de résolution circulaire.'); }
            self::need(in_array($t['bad_resolution'] ?? 'accept', ['accept','reopen'], true), 'Conséquence invalide.');
            self::string($t, 'bad_resolution_message'); self::string($t, 'expected_solution');
            if (($s['completion_status'] ?? 'resolved') === 'closed') {
                self::need(in_array('closed', $t['transitions']['resolved'] ?? [], true), 'Le critère de fin clôture exige la transition resolved → closed.');
                self::need((bool) array_filter($actions, static fn($a) => $a['type'] === 'close' && (empty($a['states']) || in_array('resolved', $a['states'], true))), 'Le critère de fin clôture exige une action close depuis resolved.');
            }
            if (Pedagogy::validation($t)) {
                $resolve = array_filter($actions, static fn($a) => $a['type'] === 'resolve' && !empty($a['repeatable']) && (empty($a['states']) || in_array('reopened', $a['states'], true)));
                if (in_array('persists', array_column($t['requester_validation']['replies'], 'outcome'), true)) { self::need((bool) $resolve, 'La réouverture exige une action resolve répétable accessible depuis reopened.'); }
                self::need((bool) array_filter($actions, static fn($a) => $a['type'] === 'close' && (empty($a['states']) || in_array('resolved', $a['states'], true))), 'Prévoir une action close accessible depuis resolved.');
            }
        }
        self::need(strlen(json_encode($s)) <= 1000000, 'Scénario limité à 1 Mo.');
        return $s;
    }
    private static function unique(array $items): void
    {
        $ids = [];
        foreach ($items as $item) {
            self::need(is_array($item) && is_string($item['id'] ?? null) && preg_match('/^[a-zA-Z0-9_-]{1,80}$/D', $item['id']), 'Identifiant invalide.');
            self::need(!isset($ids[$item['id']]), 'Identifiant dupliqué : ' . $item['id']); $ids[$item['id']] = true;
        }
    }
    private static function refs($refs, array $allowed): void
    {
        self::need(is_array($refs) && array_is_list($refs), 'Liste de références attendue.');
        foreach ($refs as $id) { self::need(is_string($id) && isset($allowed[$id]), 'Référence inconnue.'); }
    }
    private static function string(array $data, string $key, bool $required = false, int $max = 10000): void
    {
        self::need((!$required && !isset($data[$key])) || (is_string($data[$key] ?? null) && strlen($data[$key]) <= $max && (!$required || trim($data[$key]) !== '')), 'Texte invalide : ' . $key);
    }
    private static function need(bool $ok, string $message): void { if (!$ok) { throw new \InvalidArgumentException($message); } }
}
