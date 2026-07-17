<?php

namespace Tests\Feature\Xero;

use App\Events\FiscalizationRequested;
use App\Jobs\ProcessXeroWebhookEventJob;
use App\Modules\Integration\Xero\Domain\XeroConnection;
use App\Modules\Sale\Domain\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class XeroWebhookFiscalizationTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = '/api/integrations/xero/webhook';
    private const WEBHOOK_KEY = 'test-webhook-signing-key';

    protected function setUp(): void
    {
        parent::setUp();
        config(['xero.webhook_key' => self::WEBHOOK_KEY]);
    }

    #[Test]
    public function receive_only_validates_the_signature_and_queues_a_job_per_event(): void
    {
        Queue::fake();

        $response = $this->postSignedWebhook($this->webhookBody('xero-inv-9', 'tenant-9'));

        $response->assertStatus(200);
        $this->assertDatabaseCount('sales', 0);

        Queue::assertPushed(ProcessXeroWebhookEventJob::class, 1);
    }

    #[Test]
    public function invoice_created_directly_in_xero_is_mapped_and_dispatched_to_the_agent(): void
    {
        Event::fake([FiscalizationRequested::class]);

        $organization = $this->createOrganization();
        $connector = $this->createConnector($organization, ['active' => true]);
        $this->createXeroConnection($organization->getId(), 'tenant-1');

        Http::fake([
            'api.xero.com/api.xro/2.0/Invoices/xero-inv-1' => Http::response(
                $this->invoicePayload('xero-inv-1', 'AUTHORISED'),
                200
            ),
            'api.xero.com/api.xro/2.0/Contacts/contact-1' => Http::response([
                'Contacts' => [['ContactID' => 'contact-1', 'TaxNumber' => '101234567']],
            ], 200),
        ]);

        $response = $this->postSignedWebhook($this->webhookBody('xero-inv-1', 'tenant-1'));

        $response->assertStatus(200);

        $this->assertDatabaseCount('sales', 1);

        $sale = Sale::first();
        $this->assertSame($organization->getId(), $sale->getOrganizationId());
        $this->assertSame($connector->getId(), $sale->getConnectorId());
        $this->assertSame('pending_fiscal', $sale->getStatus());
        $this->assertSame('xero-inv-1', $sale->getXeroInvoiceId());
        $this->assertSame('101234567', $sale->getPayload()['buyer']['id']);
        $this->assertSame(['A'], $sale->getPayload()['items'][0]['labels']);
        $this->assertEquals(120.0, $sale->getPayload()['items'][0]['totalAmount']);

        Event::assertDispatched(FiscalizationRequested::class, fn ($event) => $event->saleId === $sale->id
            && $event->organizationId === $organization->getId()
        );
    }

    #[Test]
    public function invoice_already_registered_through_sales_endpoint_is_not_duplicated(): void
    {
        Event::fake([FiscalizationRequested::class]);

        $organization = $this->createOrganization();
        $connector = $this->createConnector($organization, ['active' => true]);
        $this->createXeroConnection($organization->getId(), 'tenant-2');

        $existingSale = $this->createSale($connector, [
            'xero_invoice_id' => 'xero-inv-2',
            'status' => 'pending_fiscal',
        ]);

        Http::fake([
            'api.xero.com/api.xro/2.0/Invoices/xero-inv-2' => Http::response(
                $this->invoicePayload('xero-inv-2', 'AUTHORISED'),
                200
            ),
        ]);

        $response = $this->postSignedWebhook($this->webhookBody('xero-inv-2', 'tenant-2'));

        $response->assertStatus(200);
        $this->assertDatabaseCount('sales', 1);

        Event::assertNotDispatched(FiscalizationRequested::class);
    }

    #[Test]
    public function draft_invoices_are_ignored(): void
    {
        Event::fake([FiscalizationRequested::class]);

        $organization = $this->createOrganization();
        $this->createConnector($organization, ['active' => true]);
        $this->createXeroConnection($organization->getId(), 'tenant-3');

        Http::fake([
            'api.xero.com/api.xro/2.0/Invoices/xero-inv-3' => Http::response(
                $this->invoicePayload('xero-inv-3', 'DRAFT'),
                200
            ),
        ]);

        $response = $this->postSignedWebhook($this->webhookBody('xero-inv-3', 'tenant-3'));

        $response->assertStatus(200);
        $this->assertDatabaseCount('sales', 0);
        Event::assertNotDispatched(FiscalizationRequested::class);
    }

    #[Test]
    public function invalid_signature_is_rejected(): void
    {
        $response = $this->postJson(self::ENDPOINT, $this->webhookBody('xero-inv-4', 'tenant-4'));

        $response->assertStatus(401);
        $this->assertDatabaseCount('sales', 0);
    }

    #[Test]
    public function responses_carry_no_cookies_as_required_by_xero(): void
    {
        // Xero requires: no cookies in the response headers, for both the
        // "intent to receive" ping and real event deliveries.
        $intentToReceive = $this->postSignedWebhook([
            'events' => [],
            'firstEventSequence' => 0,
            'lastEventSequence' => 0,
            'entropy' => 'INTENT-TO-RECEIVE',
        ]);
        $intentToReceive->assertStatus(200);
        $this->assertEmpty($intentToReceive->headers->getCookies(), 'Intent-to-receive response must not set cookies.');

        $invalidSignature = $this->postJson(self::ENDPOINT, $this->webhookBody('xero-inv-5', 'tenant-5'));
        $invalidSignature->assertStatus(401);
        $this->assertEmpty($invalidSignature->headers->getCookies(), '401 response must not set cookies either.');
    }

    private function createXeroConnection(int $organizationId, string $tenantId): XeroConnection
    {
        return XeroConnection::create([
            'organization_id' => $organizationId,
            'tenant_id' => $tenantId,
            'tenant_name' => 'Test Tenant',
            'tenant_type' => 'ORGANISATION',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addHour(),
            'active' => true,
        ]);
    }

    private function webhookBody(string $invoiceId, string $tenantId): array
    {
        return [
            'events' => [
                [
                    'resourceUrl' => "https://api.xero.com/api.xro/2.0/Invoices/{$invoiceId}",
                    'resourceId' => $invoiceId,
                    'eventDateUtc' => now()->toIso8601String(),
                    'eventType' => 'UPDATE',
                    'eventCategory' => 'INVOICE',
                    'tenantId' => $tenantId,
                    'tenantType' => 'ORGANISATION',
                ],
            ],
            'firstEventSequence' => 1,
            'lastEventSequence' => 1,
            'entropy' => 'abc123',
        ];
    }

    private function postSignedWebhook(array $body)
    {
        $content = json_encode($body);
        $signature = base64_encode(hash_hmac('sha256', $content, self::WEBHOOK_KEY, true));

        return $this->postJson(self::ENDPOINT, $body, ['x-xero-signature' => $signature]);
    }

    private function invoicePayload(string $invoiceId, string $status): array
    {
        return [
            'Id' => 'd8b072f3-4841-49f7-88ca-421d9f37f629',
            'Status' => 'OK',
            'Invoices' => [
                [
                    'Type' => 'ACCREC',
                    'InvoiceID' => $invoiceId,
                    'InvoiceNumber' => 'INV-0001',
                    'Status' => $status,
                    'Total' => 140.0,
                    'Contact' => [
                        'ContactID' => 'contact-1',
                        'Name' => 'Test Buyer Co.',
                    ],
                    'LineItems' => [
                        [
                            'ItemCode' => 'PROD1',
                            'Description' => 'PROD_TEST',
                            'UnitAmount' => 30.0,
                            'Quantity' => 4.0,
                            'LineAmount' => 120.0,
                            'AccountCode' => '200',
                        ],
                    ],
                ],
            ],
        ];
    }
}
