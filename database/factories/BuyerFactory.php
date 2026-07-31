<?php

namespace Database\Factories;

use App\Modules\Organization\Domain\Organization;
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
            'organization_id' => Organization::factory(),
            'name' => $this->faker->company(),
            'tax_id' => $this->faker->unique()->numerify('########'),
            'active' => true,
        ];
    }

    public function active(bool $active = true): self
    {
        return $this->state(fn() => ['active' => $active]);
    }

    public function inactive(): self
    {
        return $this->state(fn() => ['active' => false]);
    }

    public function forOrganization(Organization $organization): self
    {
        return $this->state(fn() => ['organization_id' => $organization->getId()]);
    }

    public function withoutTaxId(): self
    {
        return $this->state(fn() => ['tax_id' => null]);
    }
}
