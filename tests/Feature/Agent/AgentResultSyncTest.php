<?php

namespace Tests\Feature\Agent;

use App\Jobs\SendConnectorCallbackJob;
use App\Modules\Agent\Service\AgentTokenService;
use App\Modules\Connector\Domain\Connector;
use App\Modules\Integration\Xero\Domain\XeroConnection;
use App\Modules\Sale\Domain\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class AgentResultSyncTest extends TestCase
{
    use RefreshDatabase;

    // Literal public IP so delivery-time DNS checks need no network.
    private const CALLBACK_URL = 'https://93.184.216.34/webhooks/nexo';
    private const SECRET = 'whsec_test_secret';

    private Connector $connector;
    private string $agentToken;

    protected function setUp(): void
    {
        parent::setUp();

        $organization = $this->createOrganization();
        $this->connector = $this->createConnector($organization, [
            'callback_url' => self::CALLBACK_URL,
            'callback_secret' => self::SECRET,
        ]);
        $this->agentToken = app(AgentTokenService::class)->createToken($organization->getId())['token'];

        XeroConnection::create([
            'organization_id' => $organization->getId(),
            'tenant_id' => 'tenant-1',
            'tenant_name' => 'Test Tenant',
            'tenant_type' => 'ORGANISATION',
            'access_token' => 'access-token',
            'refresh_token' => 'refresh-token',
            'expires_at' => now()->addHour(),
            'active' => true,
        ]);
    }

    private function pendingSale(): Sale
    {
        return $this->createSale($this->connector, [
            'status' => 'pending_fiscal',
            'xero_invoice_id' => 'xero-inv-1',
            'attempts' => 1,
        ]);
    }

    private function postResult(Sale $sale, array $overrides = [])
    {
        return $this->withToken($this->agentToken)->postJson('/api/agent/result', array_merge([
            'sale_id' => $sale->getId(),
            'task_id' => 'task-1',
            'ok' => true,
            'fiscal_number' => 'ABCD1234-EFGH5678-9',
            'fiscal_result' => [
                'invoiceNumber' => 'ABCD1234-EFGH5678-9',
                'verificationUrl' => 'https://sandbox.taxcore.online/v/?vl=abc',
                'verificationQRCode' => base64_encode('GIF89a-fake-qr'),
                'sdcDateTime' => '2026-09-23T10:00:00',
            ],
        ], $overrides));
    }

    private function callbackRequests(): array
    {
        return Http::recorded(fn (Request $request) => $request->url() === self::CALLBACK_URL)
            ->map(fn ($pair) => $pair[0])
            ->values()
            ->all();
    }

    #[Test]
    public function successful_result_writes_fiscal_reference_to_xero_and_notifies_connector(): void
    {
        Http::fake([
            'api.xero.com/*' => Http::response(['Invoices' => [['InvoiceID' => 'xero-inv-1']]], 200),
            self::CALLBACK_URL => Http::response('', 204),
        ]);

        $sale = $this->pendingSale();

        $this->postResult($sale)->assertStatus(200);

        $sale->refresh();
        $this->assertSame('completed', $sale->getStatus());
        $this->assertNotNull($sale->getXeroFiscalSyncedAt());

        Http::assertSent(fn (Request $r) => $r->method() === 'POST'
            && $r->url() === 'https://api.xero.com/api.xro/2.0/Invoices/xero-inv-1'
            && $r['Reference'] === 'ABCD1234-EFGH5678-9');

        Http::assertSent(fn (Request $r) => $r->method() === 'PUT'
            && str_contains($r->url(), 'Invoices/xero-inv-1/Attachments/fiscal-ABCD1234-EFGH5678-9.txt?IncludeOnline=true')
            && str_contains($r->body(), 'https://sandbox.taxcore.online/v/?vl=abc'));

        Http::assertSent(fn (Request $r) => $r->method() === 'PUT'
            && str_contains($r->url(), 'Attachments/fiscal-ABCD1234-EFGH5678-9-qr.gif')
            && $r->body() === 'GIF89a-fake-qr');

        $callbacks = $this->callbackRequests();
        $events = array_map(fn (Request $r) => $r->header('X-Nexo-Event')[0], $callbacks);
        $this->assertSame(['sale.completed', 'sale.xero_fiscal_synced'], $events);

        $completed = $callbacks[0];
        $expected = hash_hmac('sha256', $completed->header('X-Nexo-Timestamp')[0] . '.' . $completed->body(), self::SECRET);
        $this->assertSame("sha256={$expected}", $completed->header('X-Nexo-Signature')[0]);
        $this->assertSame('ABCD1234-EFGH5678-9', $completed['data']['fiscal_number']);
        $this->assertSame('https://sandbox.taxcore.online/v/?vl=abc', $completed['data']['verification_url']);
    }

    #[Test]
    public function xero_sync_runs_only_once_per_sale(): void
    {
        Http::fake(['*' => Http::response(['Invoices' => [[]]], 200)]);

        $sale = $this->pendingSale();
        $sale->setXeroFiscalSyncedAt(now());
        $sale->save();

        $this->postResult($sale)->assertStatus(200);

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'api.xero.com'));
    }

    #[Test]
    public function failed_result_notifies_connector_and_skips_xero(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $sale = $this->pendingSale();

        $this->postResult($sale, ['ok' => false, 'fiscal_number' => null, 'fiscal_result' => null, 'error' => 'V-SDC returned HTTP 400'])
            ->assertStatus(200);

        $this->assertSame('failed', $sale->refresh()->getStatus());
        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), 'api.xero.com'));

        $callbacks = $this->callbackRequests();
        $this->assertCount(1, $callbacks);
        $this->assertSame('sale.failed', $callbacks[0]['event']);
        $this->assertSame('V-SDC returned HTTP 400', $callbacks[0]['data']['error_message']);
    }

    #[Test]
    public function connector_without_callback_url_receives_nothing(): void
    {
        Http::fake(['*' => Http::response(['Invoices' => [[]]], 200)]);

        $this->connector->update(['callback_url' => null, 'callback_secret' => null]);

        $this->postResult($this->pendingSale())->assertStatus(200);

        $this->assertSame([], $this->callbackRequests());
    }

    #[Test]
    public function callback_job_throws_on_non_2xx_so_the_queue_retries(): void
    {
        Http::fake([self::CALLBACK_URL => Http::response('down', 503)]);

        $this->expectException(RuntimeException::class);

        (new SendConnectorCallbackJob($this->connector->getId(), [
            'id' => 'delivery-1',
            'event' => 'sale.completed',
            'data' => [],
        ]))->handle();
    }

    #[Test]
    public function callback_job_refuses_private_addresses(): void
    {
        Http::fake();

        $this->connector->update(['callback_url' => 'https://10.0.0.5/hook']);

        (new SendConnectorCallbackJob($this->connector->getId(), [
            'id' => 'delivery-1',
            'event' => 'sale.completed',
            'data' => [],
        ]))->handle();

        Http::assertNothingSent();
    }
}
