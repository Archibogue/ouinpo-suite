<?php
namespace Ouinpo\Suite\Modules\TicketSimulator;
defined('ABSPATH') || exit;

interface TestEngineInterface
{
    /** Return prepared text/outcome only; this contract grants no execution capability. */
    public function run(array $action, array $state): array;
}
