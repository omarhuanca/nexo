<?php

namespace Tests\Feature\Organization;

use App\Modules\Organization\Domain\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationUpdateTest extends TestCase
{
    use RefreshDatabase;

    private string $endpoint = '/api/organizations';

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name'   => 'Updated Corporation',
            'tax_id' => '98765432101',
            'active' => false,
        ], $overrides);
    }

    public function test_updates_organization_successfully(): void
    {
        $org = Organization::factory()->create();

        $response = $this->putJson("{$this->endpoint}/{$org->id}", $this->validPayload());

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_response_has_expected_structure(): void
    {
        $org = Organization::factory()->create();

        $response = $this->putJson("{$this->endpoint}/{$org->id}", $this->validPayload());

        $response->assertJsonStructure([
            'success',
            'message',
            'data' => ['id', 'name', 'tax_id', 'active'],
        ]);
    }

    public function test_response_contains_updated_data(): void
    {
        $org = Organization::factory()->create();

        $response = $this->putJson("{$this->endpoint}/{$org->id}", $this->validPayload([
            'name'   => 'Updated Corporation',
            'tax_id' => '11122233344',
            'active' => false,
        ]));

        $response->assertStatus(200)
            ->assertJsonPath('data.name',   'Updated Corporation')
            ->assertJsonPath('data.tax_id', '11122233344')
            ->assertJsonPath('data.active', false);
    }

    public function test_updated_data_is_persisted_in_database(): void
    {
        $org = Organization::factory()->create();

        $this->putJson("{$this->endpoint}/{$org->id}", $this->validPayload([
            'name'   => 'Persisted Update',
            'tax_id' => '55566677788',
            'active' => true,
        ]));

        $this->assertDatabaseHas('organizations', [
            'id'     => $org->id,
            'name'   => 'Persisted Update',
            'tax_id' => '55566677788',
            'active' => true,
        ]);
    }

    public function test_can_update_with_same_name_as_current_organization(): void
    {
        $org = Organization::factory()->create(['name' => 'Same Name Corp', 'tax_id' => '12345678901']);

        $response = $this->putJson("{$this->endpoint}/{$org->id}", $this->validPayload([
            'name' => 'Same Name Corp',
        ]));

        $response->assertStatus(200);
    }

    public function test_returns_404_when_organization_does_not_exist(): void
    {
        $response = $this->putJson("{$this->endpoint}/999", $this->validPayload());

        $response->assertStatus(404)
            ->assertJsonPath('success', false);
    }

    public function test_fails_when_name_is_missing(): void
    {
        $org     = Organization::factory()->create();
        $payload = $this->validPayload();
        unset($payload['name']);

        $response = $this->putJson("{$this->endpoint}/{$org->id}", $payload);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['name']]);
    }

    public function test_fails_when_name_is_too_short(): void
    {
        $org = Organization::factory()->create();

        $response = $this->putJson("{$this->endpoint}/{$org->id}", $this->validPayload(['name' => 'AB']));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['name']]);
    }

    public function test_fails_when_name_is_too_long(): void
    {
        $org = Organization::factory()->create();

        $response = $this->putJson("{$this->endpoint}/{$org->id}", $this->validPayload([
            'name' => str_repeat('A', 256),
        ]));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['name']]);
    }

    public function test_fails_when_name_is_not_a_string(): void
    {
        $org = Organization::factory()->create();

        $response = $this->putJson("{$this->endpoint}/{$org->id}", $this->validPayload(['name' => 12345]));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['name']]);
    }

    public function test_fails_when_tax_id_is_missing(): void
    {
        $org     = Organization::factory()->create();
        $payload = $this->validPayload();
        unset($payload['tax_id']);

        $response = $this->putJson("{$this->endpoint}/{$org->id}", $payload);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['tax_id']]);
    }

    public function test_fails_when_tax_id_is_too_short(): void
    {
        $org = Organization::factory()->create();

        $response = $this->putJson("{$this->endpoint}/{$org->id}", $this->validPayload(['tax_id' => '12']));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['tax_id']]);
    }

    public function test_fails_when_tax_id_is_too_long(): void
    {
        $org = Organization::factory()->create();

        $response = $this->putJson("{$this->endpoint}/{$org->id}", $this->validPayload([
            'tax_id' => str_repeat('1', 21),
        ]));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['tax_id']]);
    }

    public function test_fails_when_active_is_missing(): void
    {
        $org     = Organization::factory()->create();
        $payload = $this->validPayload();
        unset($payload['active']);

        $response = $this->putJson("{$this->endpoint}/{$org->id}", $payload);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['active']]);
    }

    public function test_fails_when_active_is_a_string(): void
    {
        $org = Organization::factory()->create();

        $response = $this->putJson("{$this->endpoint}/{$org->id}", $this->validPayload(['active' => 'yes']));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['active']]);
    }

    public function test_fails_when_active_is_an_integer_other_than_boolean(): void
    {
        $org = Organization::factory()->create();

        $response = $this->putJson("{$this->endpoint}/{$org->id}", $this->validPayload(['active' => 5]));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['active']]);
    }

    public function test_fails_when_name_already_belongs_to_another_organization(): void
    {
        Organization::factory()->create(['name' => 'Taken Name Corp', 'tax_id' => '11111111111']);
        $org = Organization::factory()->create(['name' => 'Original Corp', 'tax_id' => '22222222222']);

        $response = $this->putJson("{$this->endpoint}/{$org->id}", $this->validPayload([
            'name' => 'Taken Name Corp',
        ]));

        $response->assertStatus(409)
            ->assertJsonPath('success', false);
    }
}
