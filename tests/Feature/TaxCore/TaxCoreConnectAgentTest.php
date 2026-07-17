<?php

namespace Tests\Feature\TaxCore;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TaxCoreConnectAgentTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function registers_a_taxcore_agent_connection(): void
    {
        $organization = $this->createOrganization();

        $response = $this->postJson('/api/integrations/taxcore/connect-agent', [
            'organization_id' => $organization->getId(),
            'environment' => 'sandbox',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.environment', 'sandbox')
            ->assertJsonPath('data.active', true);

        $this->assertDatabaseHas('taxcore_connections', [
            'organization_id' => $organization->getId(),
            'environment' => 'sandbox',
            'active' => true,
        ]);
    }
}
