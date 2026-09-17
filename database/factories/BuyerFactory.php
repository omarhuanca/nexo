<?php

namespace Database\Factories;

use App\Modules\Sale\Domain\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Modules\Buyer\Domain\Buyer>
 */
class BuyerFactory extends Factory
{
    protected $model = \App\Modules\Buyer\Domain\Buyer::class;

    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'name' => $this->faker->company(),
            'document_number' => $this->faker->unique()->numerify('########'),
        ];
    }

    public function forSale(Sale $sale): self
    {
        return $this->state(fn() => ['sale_id' => $sale->getId()]);
    }

    public function withoutDocumentNumber(): self
    {
        return $this->state(fn() => ['document_number' => null]);
    }
}
