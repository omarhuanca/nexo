<?php

namespace Database\Factories\Modules\Product\Domain;

use App\Modules\Organization\Domain\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Modules\Product\Domain\Product>
 */
class ProductFactory extends Factory
{
    protected $model = \App\Modules\Product\Domain\Product::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'code' => $this->faker->unique()->numerify('PROD-###'),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence(),
            'sale_price' => $this->faker->randomFloat(2, 10, 1000),
            'cost_price' => $this->faker->randomFloat(2, 5, 500),
        ];
    }

    public function forOrganization(Organization $organization): self
    {
        return $this->state(fn() => ['organization_id' => $organization->getId()]);
    }
}
