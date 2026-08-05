<?php

namespace App\Logging\Formatters;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

class AuditJsonFormatter extends JsonFormatter
{
    public function __construct()
    {
        parent::__construct(
            self::BATCH_MODE_NEWLINES,
            true,
            false,
            true,
        );
    }

    protected function normalizeRecord(LogRecord $record): array
    {
        $normalized = parent::normalizeRecord($record);

        $local = $record->datetime->setTimezone(new \DateTimeZone($this->appTimezone()));

        $normalized['date'] = $local->format('Y-m-d');
        $normalized['time'] = $local->format('H:i:s');
        unset($normalized['datetime']);

        return $normalized;
    }

    private function appTimezone(): string
    {
        try {
            return \config('app.timezone') ?: date_default_timezone_get();
        } catch (\Throwable) {
            return date_default_timezone_get();
        }
    }
}
