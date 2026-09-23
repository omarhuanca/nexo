<?php

namespace Tests\Feature\Xero;

use App\Events\FiscalizationRequested;
use App\Jobs\ProcessXeroWebhookEventJob;
use App\Modules\Integration\Xero\Domain\XeroConnection;
use App\Modules\Product\Domain\Product;
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

        $product = Product::where('organization_id', $organization->getId())->where('code', 'PROD1')->first();
        $this->assertNotNull($product, 'Unknown Xero item must be added to the catalog');
        $this->assertEquals(30.0, $product->sale_price);

        $this->assertSame('101234567', $sale->buyer->document_number);
        $this->assertCount(1, $sale->lineItems);
        $this->assertSame($product->id, $sale->lineItems[0]->product_id);
        $this->assertEquals(30.0, $sale->lineItems[0]->unit_price);
        $this->assertCount(1, $sale->payments);
        $this->assertEquals(140.0, $sale->payments[0]->amount);

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
    public function existing_catalog_product_is_reused_and_xero_price_wins(): void
    {
        Event::fake([FiscalizationRequested::class]);

        $organization = $this->createOrganization();
        $this->createConnector($organization, ['active' => true]);
        $this->createXeroConnection($organization->getId(), 'tenant-6');
        $product = Product::factory()->forOrganization($organization)->create(['code' => 'PROD1', 'sale_price' => 99.0]);

        Http::fake([
            'api.xero.com/api.xro/2.0/Invoices/xero-inv-6' => Http::response($this->invoicePayload('xero-inv-6', 'AUTHORISED'), 200),
            'api.xero.com/api.xro/2.0/Contacts/*' => Http::response(['Contacts' => [['TaxNumber' => null]]], 200),
        ]);

        $this->postSignedWebhook($this->webhookBody('xero-inv-6', 'tenant-6'))->assertStatus(200);

        $this->assertSame(1, Product::where('organization_id', $organization->getId())->count());
        $line = Sale::first()->lineItems[0];
        $this->assertSame($product->id, $line->product_id);
        $this->assertEquals(30.0, $line->unit_price);
    }

    #[Test]
    public function invalid_line_leaves_no_half_built_sale(): void
    {
        Event::fake([FiscalizationRequested::class]);

        $organization = $this->createOrganization();
        $this->createConnector($organization, ['active' => true]);
        $this->createXeroConnection($organization->getId(), 'tenant-7');

        $invoice = $this->invoicePayload('xero-inv-7', 'AUTHORISED');
        $invoice['Invoices'][0]['LineItems'][0]['AccountCode'] = '';

        Http::fake([
            'api.xero.com/api.xro/2.0/Invoices/xero-inv-7' => Http::response($invoice, 200),
            'api.xero.com/api.xro/2.0/Contacts/*' => Http::response(['Contacts' => [['TaxNumber' => null]]], 200),
        ]);

        $this->postSignedWebhook($this->webhookBody('xero-inv-7', 'tenant-7'))->assertStatus(200);

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('buyers', 0);
        $this->assertDatabaseCount('products', 0);
        Event::assertNotDispatched(FiscalizationRequested::class);
    }

    #[Test]
    public function description_only_lines_are_ignored(): void
    {
        Event::fake([FiscalizationRequested::class]);

        $organization = $this->createOrganization();
        $this->createConnector($organization, ['active' => true]);
        $this->createXeroConnection($organization->getId(), 'tenant-8');

        $invoice = $this->invoicePayload('xero-inv-8', 'AUTHORISED');
        $invoice['Invoices'][0]['LineItems'][] = ['Description' => 'Gracias por su compra', 'LineAmount' => 0];

        Http::fake([
            'api.xero.com/api.xro/2.0/Invoices/xero-inv-8' => Http::response($invoice, 200),
            'api.xero.com/api.xro/2.0/Contacts/*' => Http::response(['Contacts' => [['TaxNumber' => null]]], 200),
        ]);

        $this->postSignedWebhook($this->webhookBody('xero-inv-8', 'tenant-8'))->assertStatus(200);

        $this->assertCount(1, Sale::first()->lineItems);
        Event::assertDispatched(FiscalizationRequested::class);
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
