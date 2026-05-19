<?php

namespace Tests\Feature\Organization;

use App\Modules\Organization\Domain\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationStoreTest extends TestCase
{
    use RefreshDatabase;

    private string $endpoint = '/api/organizations';

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name'   => 'Acme Corporation',
            'tax_id' => '12345678901',
            'active' => true,
        ], $overrides);
    }

    // Happy path

    public function test_creates_organization_successfully(): void
    {
        $response = $this->postJson($this->endpoint, $this->validPayload());

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'name', 'tax_id', 'active'],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Acme Corporation')
            ->assertJsonPath('data.tax_id', '12345678901')
            ->assertJsonPath('data.active', true);

        $this->assertDatabaseHas('organizations', ['name' => 'Acme Corporation']);
    }

    public function test_response_has_status_201_on_creation(): void
    {
        $response = $this->postJson($this->endpoint, $this->validPayload());

        $response->assertStatus(201);
    }

    public function test_created_organization_is_persisted_in_database(): void
    {
        $this->postJson($this->endpoint, $this->validPayload([
            'name'   => 'Persisted Org',
            'tax_id' => '98765432101',
            'active' => false,
        ]));

        $this->assertDatabaseHas('organizations', [
            'name'   => 'Persisted Org',
            'tax_id' => '98765432101',
            'active' => false,
        ]);
    }

    // FormRequest validation — campo: name

    public function test_fails_when_name_is_missing(): void
    {
        $payload = $this->validPayload();
        unset($payload['name']);

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['name']]);
    }

    public function test_fails_when_name_is_too_short(): void
    {
        $response = $this->postJson($this->endpoint, $this->validPayload(['name' => 'AB']));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['name']]);
    }

    public function test_fails_when_name_is_too_long(): void
    {
        $response = $this->postJson($this->endpoint, $this->validPayload([
            'name' => str_repeat('A', 256),
        ]));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['name']]);
    }

    public function test_fails_when_name_is_not_a_string(): void
    {
        $response = $this->postJson($this->endpoint, $this->validPayload(['name' => 12345]));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['name']]);
    }

    // FormRequest validation — campo: tax_id

    public function test_fails_when_tax_id_is_missing(): void
    {
        $payload = $this->validPayload();
        unset($payload['tax_id']);

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['tax_id']]);
    }

    public function test_fails_when_tax_id_is_too_short(): void
    {
        $response = $this->postJson($this->endpoint, $this->validPayload(['tax_id' => '12']));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['tax_id']]);
    }

    public function test_fails_when_tax_id_is_too_long(): void
    {
        $response = $this->postJson($this->endpoint, $this->validPayload([
            'tax_id' => str_repeat('1', 21),
        ]));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['tax_id']]);
    }

    // FormRequest validation — campo: active

    public function test_fails_when_active_is_missing(): void
    {
        $payload = $this->validPayload();
        unset($payload['active']);

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['active']]);
    }

    public function test_fails_when_active_is_a_string(): void
    {
        $response = $this->postJson($this->endpoint, $this->validPayload(['active' => 'yes']));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['active']]);
    }

    public function test_fails_when_active_is_an_integer_other_than_boolean(): void
    {
        $response = $this->postJson($this->endpoint, $this->validPayload(['active' => 5]));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['active']]);
    }

    // Domain validation — pasa FormRequest pero falla en dominio

    public function test_fails_when_tax_id_has_invalid_format(): void
    {
        // 12 chars, pasa min:11 del FormRequest, pero contiene letras → falla el regex del dominio
        $response = $this->postJson($this->endpoint, $this->validPayload([
            'tax_id' => 'ABC-DEFGHIJK',
        ]));

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['tax_id']]);
    }

    // Business logic conflict

    public function test_fails_when_organization_name_already_exists(): void
    {
        Organization::factory()->create(['name' => 'Duplicate Corp']);

        $response = $this->postJson($this->endpoint, $this->validPayload([
            'name' => 'Duplicate Corp',
        ]));

        $response->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_duplicate_check_is_case_insensitive(): void
    {
        Organization::factory()->create(['name' => 'Acme Corp']);

        $response = $this->postJson($this->endpoint, $this->validPayload([
            'name' => 'ACME CORP',
        ]));

        $response->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    // Estructura de respuesta de error

    public function test_error_response_has_correct_structure(): void
    {
        $payload = $this->validPayload();
        unset($payload['name']);

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
            ]);
    }

    public function test_success_flag_is_false_on_validation_error(): void
    {
        $payload = $this->validPayload();
        unset($payload['name']);

        $response = $this->postJson($this->endpoint, $payload);

        $response->assertJsonPath('success', false);
    }

    public function test_success_flag_is_true_on_creation(): void
    {
        $response = $this->postJson($this->endpoint, $this->validPayload());

        $response->assertJsonPath('success', true);
    }
}
