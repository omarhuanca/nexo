<?php

namespace Database\Factories;

use App\Modules\Connector\Domain\Connector;
use App\Modules\Organization\Domain\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Connector>
 */
class ConnectorFactory extends Factory
{
    protected $model = Connector::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => $this->faker->unique()->word() . ' POS',
            'token' => hash('sha256', bin2hex(random_bytes(32))),
            'active' => true,
            'allowed_events' => null,
            'last_used_at' => null,
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn() => ['organization_id' => $organization->getId()]);
    }

    public function inactive(): static
    {
        return $this->state(fn() => ['active' => false]);
    }

    public function withAllowedEvents(array $events): static
    {
        return $this->state(fn() => ['allowed_events' => $events]);
    }
}
