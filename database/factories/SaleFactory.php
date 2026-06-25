<?php

namespace Database\Factories;

use App\Modules\Connector\Domain\Connector;
use App\Modules\Organization\Domain\Organization;
use App\Modules\Sale\Domain\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'connector_id' => Connector::factory(),
            'status' => 'pending',
            'payload' => $this->defaultPayload(),
            'xero_invoice_id' => null,
            'xero_result' => null,
            'fiscal_number' => null,
            'fiscal_result' => null,
            'error_message' => null,
            'attempts' => 0,
            'processed_at' => null,
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn() => ['organization_id' => $organization->getId()]);
    }

    public function forConnector(Connector $connector): static
    {
        return $this->state(fn() => [
            'organization_id' => $connector->getOrganizationId(),
            'connector_id' => $connector->getId(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn() => ['status' => 'pending']);
    }

    public function processing(): static
    {
        return $this->state(fn() => ['status' => 'processing', 'attempts' => 1]);
    }

    public function completed(): static
    {
        return $this->state(fn() => [
            'status' => 'completed',
            'xero_invoice_id' => 'a1b2c3d4-1234-5678-9012-abcdef123456',
            'fiscal_number' => 'CPLP77KX-TEST-1',
            'processed_at' => now(),
        ]);
    }

    public function failed(string $message = 'Test failure'): static
    {
        return $this->state(fn() => [
            'status' => 'failed',
            'attempts' => 3,
            'error_message' => $message,
        ]);
    }

    public function withPayload(array $payload): static
    {
        return $this->state(fn() => ['payload' => $payload]);
    }

    private function defaultPayload(): array
    {
        return [
            'invoiceType' => 1,
            'transactionType' => 0,
            'cashier' => $this->faker->name(),
            'buyer' => [
                'id' => $this->faker->numerify('########'),
                'name' => $this->faker->company(),
            ],
            'items' => [
                [
                    'code' => 'PROD-' . $this->faker->numerify('###'),
                    'name' => $this->faker->word(),
                    'quantity' => 1,
                    'unitPrice' => 50.00,
                    'totalAmount' => 50.00,
                    'labels' => ['A'],
                    'accountCode' => '200',
                ],
            ],
            'payment' => [
                ['amount' => 50.00, 'paymentType' => 1],
            ],
        ];
    }
}
