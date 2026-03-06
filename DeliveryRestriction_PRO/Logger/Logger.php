<?php
declare(strict_types=1);

namespace Custom\DeliveryRestriction\Logger;

use Monolog\Logger as MonologLogger;

/**
 * Dedicated PSR-3 logger — all module events route here.
 * Wired via di.xml to Logger/Handler → var/log/custom/delivery_restriction.log
 */
class Logger extends MonologLogger
{
    // All log methods (info, warning, error, debug, critical) inherited from Monolog.
}
