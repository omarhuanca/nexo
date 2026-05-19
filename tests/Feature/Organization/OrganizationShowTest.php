<?php

namespace Tests\Feature\Organization;

use App\Modules\Organization\Domain\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationShowTest extends TestCase
{
    use RefreshDatabase;

    private string $endpoint = '/api/organizations';

    public function test_returns_200_for_existing_organization(): void
    {
        $org = Organization::factory()->create();

        $response = $this->getJson("{$this->endpoint}/{$org->id}");

        $response->assertStatus(200);
    }

    public function test_response_has_expected_structure(): void
    {
        $org = Organization::factory()->create();

        $response = $this->getJson("{$this->endpoint}/{$org->id}");

        $response->assertJsonStructure([
            'success',
            'message',
            'data' => ['id', 'name', 'tax_id', 'active'],
        ]);
    }

    public function test_success_flag_is_true(): void
    {
        $org = Organization::factory()->create();

        $response = $this->getJson("{$this->endpoint}/{$org->id}");

        $response->assertJsonPath('success', true);
    }

    public function test_returns_correct_organization_data(): void
    {
        $org = Organization::factory()->create([
            'name'   => 'Acme Corporation',
            'tax_id' => '12345678901',
            'active' => true,
        ]);

        $response = $this->getJson("{$this->endpoint}/{$org->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id',     $org->id)
            ->assertJsonPath('data.name',   'Acme Corporation')
            ->assertJsonPath('data.tax_id', '12345678901')
            ->assertJsonPath('data.active', true);
    }

    public function test_returns_the_requested_organization_not_another(): void
    {
        $orgA = Organization::factory()->create(['name' => 'Alpha Corp', 'tax_id' => '11111111111']);
        $orgB = Organization::factory()->create(['name' => 'Beta Corp',  'tax_id' => '22222222222']);

        $response = $this->getJson("{$this->endpoint}/{$orgA->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id',   $orgA->id)
            ->assertJsonPath('data.name', 'Alpha Corp');

        $response->assertJsonMissingPath('data.1');
    }

    public function test_returns_404_when_organization_does_not_exist(): void
    {
        $response = $this->getJson("{$this->endpoint}/999");

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_404_response_has_expected_structure(): void
    {
        $response = $this->getJson("{$this->endpoint}/999");

        $response->assertJsonStructure(['success', 'message', 'data']);
    }

    public function test_returns_404_after_organization_was_deleted(): void
    {
        $org = Organization::factory()->create();
        $id  = $org->id;
        $org->delete();

        $response = $this->getJson("{$this->endpoint}/{$id}");

        $response->assertStatus(404);
    }
}
