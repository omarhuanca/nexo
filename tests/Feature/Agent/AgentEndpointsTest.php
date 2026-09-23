<?php

namespace Tests\Feature\Agent;

use App\Modules\Agent\Domain\AgentToken;
use App\Modules\Agent\Service\AgentTokenService;
use App\Modules\Buyer\Domain\Buyer;
use App\Modules\LineItem\Domain\LineItem;
use App\Modules\Organization\Domain\Organization;
use App\Modules\Payment\Domain\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AgentEndpointsTest extends TestCase
{
    use RefreshDatabase;

    private function createAgentToken(Organization $organization): string
    {
        return app(AgentTokenService::class)->createToken($organization->getId())['token'];
    }

    #[Test]
    public function pending_returns_taxcore_payloads_for_pending_fiscal_sales(): void
    {
        $connector = $this->createConnector($this->createOrganization());
        $organization = $connector->organization;
        $token = $this->createAgentToken($organization);

        $sale = $this->createSale($connector, ['status' => 'pending_fiscal']);
        Buyer::factory()->forSale($sale)->create(['document_number' => '12345678']);
        LineItem::factory()->forSale($sale)->create();
        Payment::factory()->forSale($sale)->cash(50.00)->create();

        $this->createSale($connector, ['status' => 'completed', 'fiscal_number' => 'DONE-1']);

        $response = $this->withToken($token)->getJson('/api/agent/pending');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.sale_id', $sale->getId())
            ->assertJsonPath('data.0.endpoint', '/api/v3/invoices')
            ->assertJsonPath('data.0.payload.buyerId', '12345678')
            ->assertJsonCount(1, 'data.0.payload.items')
            ->assertJsonPath('data.0.payload.payment.0.paymentType', 1);
    }

    #[Test]
    public function ping_updates_last_seen_and_marks_agent_online(): void
    {
        $organization = $this->createOrganization();
        $token = $this->createAgentToken($organization);
        $service = app(AgentTokenService::class);

        $this->assertFalse($service->isOnline($organization->getId()));

        $this->withToken($token)->postJson('/api/agent/ping')
            ->assertStatus(200)
            ->assertJsonPath('message', 'pong');

        $this->assertNotNull(AgentToken::query()->first()->getLastSeenAt());
        $this->assertTrue($service->isOnline($organization->getId()));
    }

    #[Test]
    public function ping_rejects_invalid_token(): void
    {
        $this->withToken('invalid')->postJson('/api/agent/ping')->assertStatus(401);
    }
}
