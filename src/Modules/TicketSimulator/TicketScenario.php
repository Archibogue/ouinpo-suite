<?php
namespace Ouinpo\Suite\Modules\TicketSimulator;
defined('ABSPATH') || exit;

final class TicketScenario
{
    public const STATUSES = ['new','accepted','diagnosing','waiting_user','waiting_specialist','escalated','resolving','resolved','closed','reopened'];
    public const ACTION_TYPES = ['take','question','consult','test','diagnostic','technical','technical_note','communication','specialist','transfer','escalate','reassign','reply','resolve','close'];
    public const FIELDS = ['requester','service','location','fictional_date','nature','category','subcategory','impact','urgency','priority','priority_justification','it_service','assignee','sla','application'];
    public static function index(array $items): array { return array_column($items, null, 'id'); }
}
