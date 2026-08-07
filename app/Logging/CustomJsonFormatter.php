<?php

namespace App\Logging;

use App\Logging\Formatters\AuditJsonFormatter;
use Illuminate\Log\Logger;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\WebProcessor;

class CustomJsonFormatter
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter(new AuditJsonFormatter());
        }

        $logger->pushProcessor(new WebProcessor());
        $logger->pushProcessor(new MemoryUsageProcessor());
    }
}
