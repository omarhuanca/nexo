<?php

namespace Tests\Feature\Organization;

use App\Modules\Organization\Domain\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationDestroyTest extends TestCase
{
    use RefreshDatabase;

    private string $endpoint = '/api/organizations';


    public function test_deletes_organization_successfully(): void
    {
        $org = Organization::factory()->create();

        $response = $this->deleteJson("{$this->endpoint}/{$org->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_response_has_expected_structure(): void
    {
        $org = Organization::factory()->create();

        $response = $this->deleteJson("{$this->endpoint}/{$org->id}");

        $response->assertJsonStructure(['success', 'message', 'data']);
    }

    public function test_organization_is_removed_from_database(): void
    {
        $org = Organization::factory()->create();
        $id  = $org->id;

        $this->deleteJson("{$this->endpoint}/{$id}");

        $this->assertDatabaseMissing('organizations', ['id' => $id]);
    }

    public function test_only_target_organization_is_deleted(): void
    {
        $orgA = Organization::factory()->create(['tax_id' => '11111111111']);
        $orgB = Organization::factory()->create(['tax_id' => '22222222222']);

        $this->deleteJson("{$this->endpoint}/{$orgA->id}");

        $this->assertDatabaseMissing('organizations', ['id' => $orgA->id]);
        $this->assertDatabaseHas('organizations',    ['id' => $orgB->id]);
    }

    public function test_returns_404_when_organization_does_not_exist(): void
    {
        $response = $this->deleteJson("{$this->endpoint}/999");

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_returns_404_when_deleting_already_deleted_organization(): void
    {
        $org = Organization::factory()->create();
        $id  = $org->id;

        $this->deleteJson("{$this->endpoint}/{$id}");

        $response = $this->deleteJson("{$this->endpoint}/{$id}");

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }
}
