<?php

namespace App\Modules\Audit\Service;

use App\Shared\Exceptions\NotFoundException;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use JsonException;
use SplFileObject;
use UnexpectedValueException;

class AuditLogService
{
    public function getLogByDate(string $date): array
    {
        $date = $this->validateDate($date);
        $filename = storage_path("logs/audit-{$date}.log");

        if (!is_file($filename) || !is_readable($filename)) {
            throw new NotFoundException("No audit log found for date {$date}");
        }

        $entries = [];
        $file = new SplFileObject($filename, 'rb');

        foreach ($file as $lineNumber => $line) {
            if (!is_string($line) || trim($line) === '') {
                continue;
            }

            try {
                $entry = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException $exception) {
                throw new UnexpectedValueException(
                    "Invalid JSON in audit log {$date} at line {$lineNumber}",
                    0,
                    $exception
                );
            }

            if (!is_array($entry)) {
                throw new UnexpectedValueException(
                    "Invalid audit entry in log {$date} at line {$lineNumber}"
                );
            }

            $entries[] = $entry;
        }

        return $entries;
    }

    private function validateDate(string $date): string
    {
        $parsedDate = CarbonImmutable::createFromFormat('!Y-m-d', $date);
        $errors = CarbonImmutable::getLastErrors();

        if (
            $parsedDate === false
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $parsedDate->format('Y-m-d') !== $date
        ) {
            throw new InvalidArgumentException('Audit log date must use the Y-m-d format.');
        }

        return $parsedDate->format('Y-m-d');
    }
}
