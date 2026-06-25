<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Formatter\JsonFormatter;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\WebProcessor;

class CustomJsonFormatter
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter(new JsonFormatter(JsonFormatter::BATCH_MODE_NEWLINES, true));
        }

        $logger->pushProcessor(new WebProcessor());
        $logger->pushProcessor(new MemoryUsageProcessor());
    }
}
