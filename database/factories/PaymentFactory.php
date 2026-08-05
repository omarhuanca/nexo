<?php

namespace Database\Factories;

use App\Modules\Sale\Domain\Sale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Modules\Payment\Domain\Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = \App\Modules\Payment\Domain\Payment::class;

    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'amount' => $this->faker->randomFloat(2, 10, 1000),
            'payment_type' => $this->faker->randomElement([
                \App\Modules\Payment\Domain\Payment::PAYMENT_TYPE_CASH,
                \App\Modules\Payment\Domain\Payment::PAYMENT_TYPE_CARD,
            ]),
            'sequence' => 0,
        ];
    }

    public function forSale(Sale $sale): self
    {
        return $this->state(fn() => ['sale_id' => $sale->getId()]);
    }

    public function cash(float $amount = 100.00): self
    {
        return $this->state(fn() => [
            'amount' => $amount,
            'payment_type' => \App\Modules\Payment\Domain\Payment::PAYMENT_TYPE_CASH,
        ]);
    }

    public function card(float $amount = 100.00): self
    {
        return $this->state(fn() => [
            'amount' => $amount,
            'payment_type' => \App\Modules\Payment\Domain\Payment::PAYMENT_TYPE_CARD,
        ]);
    }

    public function withSequence(int $sequence): self
    {
        return $this->state(fn() => ['sequence' => $sequence]);
    }
}
