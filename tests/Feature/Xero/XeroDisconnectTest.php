<?php

namespace Tests\Feature\Xero;

use App\Events\Xero\XeroConnectionDisconnected;
use App\Events\Xero\XeroConnectionReplaced;
use App\Modules\Integration\Xero\Domain\XeroConnection;
use App\Modules\Integration\Xero\Service\XeroOauthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class XeroDisconnectTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function disconnecting_an_active_connection_revokes_it_and_deactivates_it(): void
    {
        Event::fake([XeroConnectionDisconnected::class]);
        Http::fake([
            'identity.xero.com/connect/revocation' => Http::response([], 200),
        ]);

        $organization = $this->createOrganization();
        $connection = $this->createXeroConnection($organization->getId(), 'tenant-1');

        $response = $this->deleteJson('/api/integrations/xero/disconnect?organization_id=' . $organization->getId());

        $response->assertStatus(200);

        $this->assertFalse($connection->fresh()->getActive());

        Http::assertSent(fn ($request) => str_contains($request->url(), 'identity.xero.com/connect/revocation'));

        Event::assertDispatched(XeroConnectionDisconnected::class, fn ($event) => $event->organizationId === $organization->getId()
            && $event->tenantId === 'tenant-1'
        );
    }

    #[Test]
    public function disconnecting_without_an_active_connection_returns_not_found(): void
    {
        $organization = $this->createOrganization();

        $response = $this->deleteJson('/api/integrations/xero/disconnect?organization_id=' . $organization->getId());

        $response->assertStatus(404);
    }

    #[Test]
    public function reconnecting_to_the_same_tenant_after_disconnecting_reactivates_it(): void
    {
        $organization = $this->createOrganization();
        $connection = $this->createXeroConnection($organization->getId(), 'tenant-same');
        $connection->setActive(false);
        $connection->save();

        $authUrl = app(XeroOauthService::class)->getAuthorizationUrl($organization->getId());
        parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $query);
        $state = $query['state'];

        Http::fake([
            'identity.xero.com/connect/token' => Http::response([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 1800,
                'scope' => 'openid accounting.transactions',
            ], 200),
            'api.xero.com/connections' => Http::response([
                ['tenantId' => 'tenant-same', 'tenantName' => 'Test Tenant', 'tenantType' => 'ORGANISATION'],
            ], 200),
        ]);

        $response = $this->get('/api/integrations/xero/callback?code=auth-code&state=' . urlencode($state));

        $response->assertRedirect();
        $this->assertStringContainsString('xero=connected', $response->headers->get('Location'));

        $this->assertTrue($connection->fresh()->getActive());
        $this->assertSame(1, XeroConnection::where('organization_id', $organization->getId())->where('active', true)->count());
    }

    #[Test]
    public function reconnecting_with_a_different_tenant_deactivates_the_previous_connection(): void
    {
        Event::fake([XeroConnectionReplaced::class]);

        $organization = $this->createOrganization();
        $oldConnection = $this->createXeroConnection($organization->getId(), 'tenant-old');

        $authUrl = app(XeroOauthService::class)->getAuthorizationUrl($organization->getId());
        parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $query);
        $state = $query['state'];

        Http::fake([
            'identity.xero.com/connect/token' => Http::response([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 1800,
                'scope' => 'openid accounting.transactions',
            ], 200),
            'api.xero.com/connections' => Http::response([
                ['tenantId' => 'tenant-new', 'tenantName' => 'New Tenant', 'tenantType' => 'ORGANISATION'],
            ], 200),
            // Simulate Xero rejecting the revocation of the old token — the switch must still succeed locally.
            'identity.xero.com/connect/revocation' => Http::response([], 400),
        ]);

        $response = $this->get('/api/integrations/xero/callback?code=auth-code&state=' . urlencode($state));

        $response->assertRedirect();
        $this->assertStringContainsString('xero=connected', $response->headers->get('Location'));

        $this->assertFalse($oldConnection->fresh()->getActive());

        $newConnection = XeroConnection::where('tenant_id', 'tenant-new')->first();
        $this->assertNotNull($newConnection);
        $this->assertTrue($newConnection->getActive());
        $this->assertSame($organization->getId(), $newConnection->getOrganizationId());

        Event::assertDispatched(XeroConnectionReplaced::class, fn ($event) => $event->organizationId === $organization->getId()
            && $event->oldTenantId === 'tenant-old'
            && $event->newTenantId === 'tenant-new'
        );
    }

    #[Test]
    public function callback_in_popup_mode_returns_a_postmessage_page_instead_of_a_redirect(): void
    {
        $organization = $this->createOrganization();

        $authUrl = app(XeroOauthService::class)->getAuthorizationUrl($organization->getId(), 'popup');
        parse_str((string) parse_url($authUrl, PHP_URL_QUERY), $query);
        $state = $query['state'];

        Http::fake([
            'identity.xero.com/connect/token' => Http::response([
                'access_token' => 'access-token',
                'refresh_token' => 'refresh-token',
                'expires_in' => 1800,
                'scope' => 'openid accounting.transactions',
            ], 200),
            'api.xero.com/connections' => Http::response([
                ['tenantId' => 'tenant-popup', 'tenantName' => 'Popup Tenant', 'tenantType' => 'ORGANISATION'],
            ], 200),
        ]);

        $response = $this->get('/api/integrations/xero/callback?code=auth-code&state=' . urlencode($state));

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        $response->assertSee('window.opener.postMessage', false);
        $response->assertSee('"status":"connected"', false);
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
}
