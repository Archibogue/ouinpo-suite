<?php
namespace Ouinpo\Suite\Modules\TicketSimulator;
defined('ABSPATH') || exit;

final class TicketIntake
{
    public const FIELDS = ['title','requester','service','application','symptoms','missing_information','questions'];
    public const LABELS = ['title'=>'Titre','requester'=>'Demandeur identifié','service'=>'Service concerné','application'=>'Application concernée','symptoms'=>'Symptômes / besoin décrit','missing_information'=>'Informations manquantes','questions'=>'Questions complémentaires préparées'];
    public static function enabled(array $ticket): bool { return ($ticket['intake_mode'] ?? 'prepared') === 'from_request'; }
    public static function save(array $ticket, array $state, array $input): array
    {
        if (!self::enabled($ticket)) { throw new \DomainException('Ce scénario utilise un ticket déjà préparé.'); }
        if (in_array($state['status'], ['resolved','closed'], true) || !empty($state['pending'])) { throw new \DomainException('Reprenez le traitement avant de modifier la fiche.'); }
        $draft = [];
        foreach (self::FIELDS as $key) {
            if (!is_string($input[$key] ?? '') || strlen($input[$key] ?? '') > 10000) { throw new \InvalidArgumentException('Champ de fiche invalide.'); }
            $draft[$key] = trim($input[$key] ?? '');
        }
        if ($draft['title'] === '' || $draft['requester'] === '' || $draft['symptoms'] === '' || ($draft['service'] === '' && $draft['application'] === '')) {
            throw new \DomainException('Renseignez le titre, le demandeur, les symptômes ou le besoin, et au moins le service ou l’application. Vous pouvez indiquer une information à confirmer.');
        }
        $state['intake'] = $draft;
        $state['fields']['service'] = $draft['service'];
        $state['fields']['application'] = $draft['application'];
        $lines = [];
        foreach (self::LABELS as $key => $label) { $lines[] = $label . ' : ' . ($draft[$key] !== '' ? $draft[$key] : 'Non renseigné'); }
        return [$state, [['type'=>'intake', 'text'=>implode("\n", $lines)]]];
    }
}
