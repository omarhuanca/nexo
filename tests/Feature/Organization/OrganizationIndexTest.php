<?php

namespace Tests\Feature\Organization;

use App\Modules\Organization\Domain\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationIndexTest extends TestCase
{
    use RefreshDatabase;

    private string $endpoint = '/api/organizations';

    public function test_returns_200_status(): void
    {
        $response = $this->getJson($this->endpoint);

        $response->assertStatus(200);
    }

    public function test_response_has_expected_structure(): void
    {
        $response = $this->getJson($this->endpoint);

        $response->assertJsonStructure([
            'success',
            'message',
            'data',
            'pagination' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
                'from',
                'to',
            ],
        ]);
    }

    public function test_success_flag_is_true(): void
    {
        $response = $this->getJson($this->endpoint);

        $response->assertJsonPath('success', true);
    }

    public function test_returns_empty_data_when_no_organizations_exist(): void
    {
        $response = $this->getJson($this->endpoint);

        $response->assertStatus(200)
            ->assertJsonPath('data', [])
            ->assertJsonPath('pagination.total', 0);
    }

    public function test_data_items_contain_correct_fields(): void
    {
        Organization::factory()->create([
            'name'   => 'Acme Corp',
            'tax_id' => '12345678901',
            'active' => true,
        ]);

        $response = $this->getJson($this->endpoint);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'tax_id', 'active'],
                ],
            ]);
    }

    public function test_returns_all_organizations(): void
    {
        Organization::factory()->count(3)->create();

        $response = $this->getJson($this->endpoint);

        $response->assertStatus(200)
            ->assertJsonPath('pagination.total', 3)
            ->assertJsonCount(3, 'data');
    }

    public function test_pagination_metadata_reflects_correct_values(): void
    {
        Organization::factory()->count(3)->create();

        $response = $this->getJson($this->endpoint . '?perPage=10');

        $response->assertStatus(200)
            ->assertJsonPath('pagination.current_page', 1)
            ->assertJsonPath('pagination.per_page', 10)
            ->assertJsonPath('pagination.total', 3);
    }

    public function test_per_page_parameter_limits_number_of_items(): void
    {
        Organization::factory()->count(5)->create();

        $response = $this->getJson($this->endpoint . '?perPage=2');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('pagination.per_page', 2)
            ->assertJsonPath('pagination.total', 5)
            ->assertJsonPath('pagination.last_page', 3);
    }

    public function test_per_page_50_returns_all_items_in_single_page(): void
    {
        Organization::factory()->count(30)->create();

        $response = $this->getJson($this->endpoint . '?perPage=50');

        $response->assertStatus(200)
            ->assertJsonPath('pagination.per_page', 50)
            ->assertJsonPath('pagination.total', 30)
            ->assertJsonPath('pagination.current_page', 1)
            ->assertJsonPath('pagination.last_page', 1)
            ->assertJsonCount(30, 'data');
    }

    public function test_page_2_returns_correct_slice(): void
    {
        Organization::factory()->count(3)->create();

        $response = $this->getJson($this->endpoint . '?perPage=2&page=2');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('pagination.current_page', 2);
    }

    public function test_from_and_to_pagination_values_are_correct(): void
    {
        Organization::factory()->count(5)->create();

        $response = $this->getJson($this->endpoint . '?perPage=3');

        $response->assertStatus(200)
            ->assertJsonPath('pagination.from', 1)
            ->assertJsonPath('pagination.to', 3);
    }

    public function test_search_filters_results_by_name(): void
    {
        Organization::factory()->create(['name' => 'Acme Corporation', 'tax_id' => '11111111111']);
        Organization::factory()->create(['name' => 'Beta Industries', 'tax_id' => '22222222222']);

        $response = $this->getJson($this->endpoint . '?search=Acme');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Acme Corporation');
    }

    public function test_search_is_case_insensitive(): void
    {
        Organization::factory()->create(['name' => 'Acme Corporation', 'tax_id' => '11111111111']);

        $response = $this->getJson($this->endpoint . '?search=acme');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_search_returns_empty_when_no_match(): void
    {
        Organization::factory()->create(['name' => 'Acme Corporation', 'tax_id' => '11111111111']);

        $response = $this->getJson($this->endpoint . '?search=nonexistent');

        $response->assertStatus(200)
            ->assertJsonPath('data', [])
            ->assertJsonPath('pagination.total', 0);
    }

    public function test_search_does_partial_name_match(): void
    {
        Organization::factory()->create(['name' => 'Acme Corporation', 'tax_id' => '11111111111']);
        Organization::factory()->create(['name' => 'Acme Logistics',   'tax_id' => '22222222222']);
        Organization::factory()->create(['name' => 'Beta Industries',  'tax_id' => '33333333333']);

        $response = $this->getJson($this->endpoint . '?search=Acme');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }
}
