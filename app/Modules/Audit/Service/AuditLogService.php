<?php

namespace App\Modules\Audit\Service;

use App\Shared\Exceptions\NotFoundException;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use JsonException;
use Illuminate\Pagination\LengthAwarePaginator;
use SplFileObject;
use UnexpectedValueException;

class AuditLogService
{
    public function paginateLogByDate(string $date, int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        if ($page < 1) {
            throw new InvalidArgumentException('page must be >= 1.');
        }

        if ($perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('perPage must be between 1 and 100.');
        }

        $date = $this->validateDate($date);
        $file = $this->openLogFile($date);
        $offset = ($page - 1) * $perPage;
        $entries = [];
        $total = 0;

        foreach ($file as $lineNumber => $line) {
            if (!is_string($line) || trim($line) === '') {
                continue;
            }

            $entry = $this->decodeEntry($line, $date, $lineNumber);

            if ($total >= $offset && count($entries) < $perPage) {
                $entries[] = $entry;
            }

            $total++;
        }

        return new LengthAwarePaginator(
            $entries,
            $total,
            $perPage,
            $page
        );
    }

    private function openLogFile(string $date): SplFileObject
    {
        $filename = storage_path("logs/audit-{$date}.log");

        if (!is_file($filename) || !is_readable($filename)) {
            throw new NotFoundException("No audit log found for date {$date}");
        }

        return new SplFileObject($filename, 'rb');
    }

    private function decodeEntry(string $line, string $date, int $lineNumber): array
    {
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

        return $entry;
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
