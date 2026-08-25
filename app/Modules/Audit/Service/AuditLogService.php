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
    public function paginateAllLogs(string $cursor = '', int $perPage = 15): array {
        if ($perPage < 1 || $perPage > 100) {
            throw new InvalidArgumentException('perPage must be between 1 and 100.');
        }

        $files = $this->getAuditFiles();
        $fileIndex = 0;
        $entryIndex = 0;
        $cursorFileIndex = 0;

        if ($cursor !== '') {
            $position = $this->decodeCursor($cursor);
            $fileIndex = array_search($position['file'], $files, true);

            if ($fileIndex === false) {
            throw new InvalidArgumentException('Invalid audit log cursor.');
            }

            $entryIndex = $position['entry'];
            $cursorFileIndex = $fileIndex;
        }

        $entries = [];
        $nextCursor = '';
        $hasMore = false;

        for (; $fileIndex < count($files); $fileIndex++) {
            $filename = $files[$fileIndex];
            $date = $this->extractDateFromFilename($filename);
            $fileEntries = $this->readEntriesFromFile($date);

            $currentEntryIndex = $fileIndex === $cursorFileIndex
                ? $entryIndex
                : 0;

            for ($currentEntryIndex; $currentEntryIndex < count($fileEntries); $currentEntryIndex++) {
                $entries[] = $fileEntries[$currentEntryIndex];

                if (count($entries) >= $perPage) {
                    $nextEntryIndex = $currentEntryIndex + 1;
                    $hasMore = $nextEntryIndex < count($fileEntries) || $fileIndex + 1 < count($files);

                    if ($hasMore) {
                        $nextCursor = $this->encodeCursor(['file' => $filename, 'entry' => $nextEntryIndex]);
                    }

                    break 2;
                }
            }
        }

        return [
            'entries' => $entries,
            'pagination' => [
                'per_page' => $perPage,
                'has_more' => $hasMore,
                'next_cursor' => $nextCursor,
            ],
        ];
    }
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

    private function readEntriesFromFile(string $date): array
    {
        $file = $this->openLogFile($date);
        $entries = [];

        foreach ($file as $lineNumber => $line) {
            if (!is_string($line) || trim($line) === '') {
                continue;
            }

            $entry = $this->decodeEntry($line, $date, $lineNumber);

            $entry['log_date'] = $date;
            $entry['log_file'] = "audit-{$date}.log";
            $entries[] = $entry;

        }

        return array_reverse($entries);
    }

    private function getAuditFiles(): array
    {
        $files = glob(storage_path('logs/audit-*.log')) ?: [];

        $files = array_filter($files, $this->isReadableFile(...));

        usort($files, $this->compareFilesByNameDescending(...));

        return array_values(array_map('basename', $files));

    }

    private function extractDateFromFilename(string $filename): string
    {
        if (!preg_match('/^audit-(\d{4}-\d{2}-\d{2})\.log$/', $filename, $matches)) {
                throw new InvalidArgumentException('Invalid audit log filename.');
            }

            return $this->validateDate($matches[1]);
    }

    private function encodeCursor(array $position): string
    {
        $json = json_encode($position, JSON_THROW_ON_ERROR );

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    private function decodeCursor(string $cursor): array
    {
        $padding = strlen($cursor) % 4;

        if ($padding !== 0) {
            $cursor .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($cursor, '-_', '+/'),true);

        if ($decoded === false) {
            throw new InvalidArgumentException('Invalid audit log cursor.');
        }

        try {
            $position = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);

        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Invalid audit log cursor.', 0, $exception);
        }

        if (!is_array($position) || !isset($position['file'], $position['entry']) || !is_string($position['file']) || !is_int($position['entry']) || $position['entry'] < 0) {
            throw new InvalidArgumentException('Invalid audit log cursor.');
        }
        return $position;
    }

    private function isReadableFile(string $file): bool{
        return is_file($file) && is_readable($file);
    }

    private function compareFilesByNameDescending(string $left, string $right): int {
        return strcmp(basename($right),basename($left));
    }
}
