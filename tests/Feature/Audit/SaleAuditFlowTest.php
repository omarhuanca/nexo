<?php

namespace Tests\Feature\Audit;

use App\Jobs\ProcessSaleJob;
use App\Modules\Connector\Service\ConnectorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SaleAuditFlowTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/sales';

    private string $connectorToken;
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $organization = $this->createOrganization(['name' => 'Audit Flow Co.']);
        $connector = app(ConnectorService::class)->createConnector(
            $organization->getId(),
            'Test POS',
            [],
            true
        );
        $this->connectorToken = $connector->plainToken;
        $this->headers = ['Authorization' => "Bearer {$this->connectorToken}"];

        $this->cleanAuditLog();
    }

    #[Test]
    public function posting_a_sale_writes_sale_submitted_event_to_audit_log(): void
    {
        Queue::fake();
        $payload = $this->validPayload();

        $response = $this->postJson(self::ENDPOINT, $payload, $this->headers);

        $response->assertStatus(202)
            ->assertJsonPath('data.status', 'pending');

        $entries = $this->readNewAuditEntries();

        $this->assertGreaterThanOrEqual(1, count($entries));

        $saleEvent = collect($entries)->first(fn($e) =>
            ($e['context']['event'] ?? null) === 'sale.submitted'
        );
        $this->assertNotNull($saleEvent);
        $this->assertSame('sale.submitted', $saleEvent['context']['event']);
        $this->assertSame('Sale submitted for processing', $saleEvent['message']);
        $this->assertGreaterThan(0, $saleEvent['context']['sale_id']);
        $this->assertSame(1, $saleEvent['context']['organization_id']);
        $this->assertSame(1, $saleEvent['context']['connector_id']);
        $this->assertSame(1, $saleEvent['context']['invoice_type']);
        $this->assertSame(0, $saleEvent['context']['transaction_type']);
        $this->assertSame(1, $saleEvent['context']['items_count']);
        $this->assertEquals(50, $saleEvent['context']['total_amount']);

        Queue::assertPushed(ProcessSaleJob::class);
    }

    #[Test]
    public function posting_a_sale_writes_connector_authenticated_event_to_audit_log(): void
    {
        Queue::fake();
        $this->postJson(self::ENDPOINT, $this->validPayload(), $this->headers);

        $entries = $this->readNewAuditEntries();

        $authEvents = array_values(array_filter($entries, fn($e) =>
            ($e['context']['event'] ?? null) === 'auth.connector.success'
        ));

        $this->assertCount(1, $authEvents);
        $this->assertSame(1, $authEvents[0]['context']['connector_id']);
        $this->assertSame(1, $authEvents[0]['context']['organization_id']);
    }

    #[Test]
    public function unauthenticated_sale_request_writes_no_sale_submitted_audit_entry(): void
    {
        $this->postJson(self::ENDPOINT, $this->validPayload());

        $entries = $this->readNewAuditEntries();

        $saleEvents = array_filter($entries, fn($e) =>
            ($e['context']['event'] ?? null) === 'sale.submitted'
        );

        $this->assertCount(0, $saleEvents);
    }

    #[Test]
    public function unauthenticated_sale_request_writes_auth_failed_audit_entry(): void
    {
        $this->postJson(self::ENDPOINT, $this->validPayload());

        $entries = $this->readNewAuditEntries();

        $authEvents = array_filter($entries, fn($e) =>
            ($e['context']['event'] ?? null) === 'auth.connector.failed'
        );

        $this->assertGreaterThanOrEqual(1, count($authEvents));
    }

    #[Test]
    public function invalid_sale_payload_writes_no_sale_submitted_audit_entry(): void
    {
        $this->postJson(self::ENDPOINT, ['invoiceType' => 1], $this->headers)
            ->assertStatus(422);

        $entries = $this->readNewAuditEntries();

        $saleEvents = array_filter($entries, fn($e) =>
            ($e['context']['event'] ?? null) === 'sale.submitted'
        );

        $this->assertCount(0, $saleEvents);
    }

    private function validPayload(): array
    {
        return [
            'invoiceType' => 1,
            'transactionType' => 0,
            'cashier' => 'Test Cashier',
            'buyer' => [
                'name' => 'Test Buyer Co.',
                'id' => '12345678',
            ],
            'items' => [
                [
                    'code' => 'PROD-001',
                    'name' => 'Test Product',
                    'quantity' => 1,
                    'unitPrice' => 50.00,
                    'totalAmount' => 50.00,
                    'labels' => ['A'],
                    'accountCode' => '200',
                ],
            ],
            'payment' => [
                ['amount' => 50.00, 'paymentType' => 1],
            ],
        ];
    }

    private function cleanAuditLog(): void
    {
        $logFile = storage_path('logs/audit-' . date('Y-m-d') . '.log');
        if (file_exists($logFile)) {
            unlink($logFile);
        }
    }

    private function readNewAuditEntries(): array
    {
        $logFile = storage_path('logs/audit-' . date('Y-m-d') . '.log');

        if (!file_exists($logFile)) {
            return [];
        }

        $content = file_get_contents($logFile);
        $entries = [];
        foreach (array_filter(explode("\n", $content)) as $line) {
            $decoded = json_decode($line, true);
            if ($decoded !== null) {
                $entries[] = $decoded;
            }
        }
        return $entries;
    }
}
