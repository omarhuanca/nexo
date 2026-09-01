<?php

namespace Tests\Unit\Audit;

use App\Modules\Audit\Service\AuditLogService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(AuditLogService::class)]
class AuditLogServiceTest extends TestCase
{
    private string $logDirectory;
    private AuditLogService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logDirectory = storage_path('framework/testing/audit-logs');
        $this->createLogDirectory();
        $this->removeTestLogs();
        $this->service = new AuditLogService($this->logDirectory);
    }

    protected function tearDown(): void
    {
        $this->removeTestLogs();

        parent::tearDown();
    }

    #[Test]
    public function it_returns_entries_from_newest_file_first(): void
    {
        $this->writeLog('audit-2026-08-01.log', [
            ['message' => 'Older entry'],
        ]);
        $this->writeLog('audit-2026-08-02.log', [
            ['message' => 'Newer entry'],
        ]);

        $result = $this->service->paginateAllLogs('', 10);
        $messages = array_column($result['entries'], 'message');

        $this->assertSame(
            ['Newer entry', 'Older entry'],
            $messages
        );
    }

    #[Test]
    public function it_combines_entries_from_multiple_files_to_fill_a_page(): void
    {
        $this->writeLog('audit-2026-08-02.log', [
            ['message' => 'New 1'],
            ['message' => 'New 2'],
        ]);
        $this->writeLog('audit-2026-08-01.log', [
            ['message' => 'Old 1'],
            ['message' => 'Old 2'],
            ['message' => 'Old 3'],
        ]);

        $result = $this->service->paginateAllLogs('', 4);
        $messages = array_column($result['entries'], 'message');

        $this->assertSame(
            ['New 2', 'New 1', 'Old 3', 'Old 2'],
            $messages
        );
        $this->assertTrue($result['pagination']['has_more']);
        $this->assertNotSame('', $result['pagination']['next_cursor']);
    }

    #[Test]
    public function it_continues_from_the_cursor_without_repeating_entries(): void
    {
        $this->writeLog('audit-2026-08-02.log', [
            ['message' => 'New 1'],
            ['message' => 'New 2'],
        ]);
        $this->writeLog('audit-2026-08-01.log', [
            ['message' => 'Old 1'],
            ['message' => 'Old 2'],
        ]);

        $firstPage = $this->service->paginateAllLogs('', 2);
        $secondPage = $this->service->paginateAllLogs(
            $firstPage['pagination']['next_cursor'],
            2
        );

        $firstMessages = array_column($firstPage['entries'], 'message');
        $secondMessages = array_column($secondPage['entries'], 'message');

        $this->assertSame(['New 2', 'New 1'], $firstMessages);
        $this->assertSame(['Old 2', 'Old 1'], $secondMessages);
        $this->assertSame([], array_intersect($firstMessages, $secondMessages));
    }

    #[Test]
    public function it_returns_an_empty_cursor_when_there_are_no_more_entries(): void
    {
        $this->writeLog('audit-2026-08-01.log', [
            ['message' => 'Only entry'],
        ]);

        $result = $this->service->paginateAllLogs('', 10);

        $this->assertFalse($result['pagination']['has_more']);
        $this->assertSame('', $result['pagination']['next_cursor']);
    }

    #[Test]
    public function it_rejects_an_invalid_cursor(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->paginateAllLogs('invalid-cursor', 10);
    }

    #[Test]
    public function it_rejects_an_invalid_per_page_value(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service->paginateAllLogs('', 0);
    }

    private function createLogDirectory(): void
    {
        if (!is_dir($this->logDirectory)) {
            mkdir($this->logDirectory, 0777, true);
        }
    }

    private function writeLog(string $filename, array $entries): void
    {
        $lines = array_map(
            $this->encodeEntry(...),
            $entries
        );

        file_put_contents(
            $this->logDirectory . DIRECTORY_SEPARATOR . $filename,
            implode(PHP_EOL, $lines) . PHP_EOL
        );
    }

    private function encodeEntry(array $entry): string
    {
        return json_encode($entry, JSON_THROW_ON_ERROR);
    }

    private function removeTestLogs(): void
    {
        if (!is_dir($this->logDirectory)) {
            return;
        }

        foreach (glob($this->logDirectory . DIRECTORY_SEPARATOR . 'audit-*.log') ?: [] as $file) {
            unlink($file);
        }
    }
}
