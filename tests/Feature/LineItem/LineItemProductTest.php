<?php

namespace Tests\Feature\LineItem;

use App\Modules\Connector\Domain\Connector;
use App\Modules\Organization\Domain\Organization;
use App\Modules\Product\Domain\Product;
use App\Modules\Sale\Domain\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LineItemProductTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private Connector $connector;
    private Sale $sale;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->connector = Connector::factory()->forOrganization($this->organization)->create();
        $this->sale = Sale::factory()->forConnector($this->connector)->create();
        $this->product = Product::factory()->forOrganization($this->organization)->create([
            'code' => 'PROD-001',
            'name' => 'Test Product',
            'sale_price' => 50.0,
        ]);
    }

    public function test_create_line_item_requires_product_id(): void
    {
        $response = $this->postJson(
            "/api/sales/{$this->sale->id}/lineItems",
            [
                'quantity' => 1.0,
                'total_amount' => 50.0,
                'labels' => ['A'],
                'account_code' => '200',
            ]
        );

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);
    }

    public function test_create_line_item_persists_product_reference(): void
    {
        $response = $this->postJson(
            "/api/sales/{$this->sale->id}/lineItems",
            [
                'product_id' => $this->product->id,
                'quantity' => 2.0,
                'total_amount' => 100.0,
                'labels' => ['A'],
                'account_code' => '200',
            ]
        );

        $response->assertCreated();

        $this->assertDatabaseHas('line_items', [
            'sale_id' => $this->sale->id,
            'product_id' => $this->product->id,
            'code' => 'PROD-001',
            'name' => 'Test Product',
            'quantity' => 2.0,
            'unit_price' => 50.0,
            'total_amount' => 100.0,
        ]);
    }
}
