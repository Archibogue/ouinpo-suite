<?php
namespace Ouinpo\Suite\Modules\TicketSimulator;
defined('ABSPATH') || exit;

final class TicketResource
{
    public const TYPES = ['application','source','script','database','sql_table','server','workstation','network_device','network_service','configuration','log','documentation','procedure','attachment'];
    // Content is always stored text, never a filesystem path to resolve.
    public static function publicData(array $resource): array
    {
        return array_intersect_key($resource, array_flip(['id','label','type','filename','language','content','editable']));
    }
}
