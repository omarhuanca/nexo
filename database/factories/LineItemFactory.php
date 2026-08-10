<?php

namespace Database\Factories;

use App\Modules\Sale\Domain\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Modules\LineItem\Domain\LineItem>
 */
class LineItemFactory extends Factory
{
    protected $model = \App\Modules\LineItem\Domain\LineItem::class;

    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(4, 1, 100);
        $unitPrice = $this->faker->randomFloat(4, 10, 500);

        return [
            'sale_id' => Sale::factory(),
            'code' => 'PROD-' . $this->faker->unique()->numerify('####'),
            'name' => $this->faker->words(3, true),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_amount' => round($quantity * $unitPrice, 4),
            'labels' => ['A'],
            'account_code' => '200',
            'gtin' => null,
        ];
    }

    public function forSale(Sale $sale): self
    {
        return $this->state(fn() => ['sale_id' => $sale->getId()]);
    }

    public function withCode(string $code): self
    {
        return $this->state(fn() => ['code' => $code]);
    }

    public function withGtin(string $gtin): self
    {
        return $this->state(fn() => ['gtin' => $gtin]);
    }
}
