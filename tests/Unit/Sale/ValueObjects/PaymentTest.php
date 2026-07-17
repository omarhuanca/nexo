<?php

namespace Tests\Unit\Sale\ValueObjects;

use App\Modules\Sale\Domain\ValueObjects\Payment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(Payment::class)]
class PaymentTest extends ValueObjectTestCase
{
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'amount'      => 100.00,
            'paymentType' => 1,
        ], $overrides);
    }

    #[Test]
    public function it_builds_a_valid_payment(): void
    {
        $payment = Payment::fromArray($this->validPayload());

        $this->assertSame(100.0, $payment->amount);
        $this->assertSame(1, $payment->paymentType);
    }

    #[Test]
    public function it_casts_int_amount_to_float(): void
    {
        $payment = Payment::fromArray($this->validPayload(['amount' => 100]));

        $this->assertSame(100.0, $payment->amount);
    }

    #[Test]
    public function it_accepts_zero_payment_type(): void
    {
        $payment = Payment::fromArray($this->validPayload(['paymentType' => 0]));
        $this->assertSame(0, $payment->paymentType);
    }

    #[Test]
    public function it_accepts_all_whitelisted_payment_types(): void
    {
        foreach (Payment::VALID_PAYMENT_TYPES as $type) {
            $payment = Payment::fromArray($this->validPayload(['paymentType' => $type]));
            $this->assertSame($type, $payment->paymentType);
        }
    }

    #[Test]
    public function it_rejects_zero_amount(): void
    {
        $this->assertDomainError(
            fn () => Payment::fromArray($this->validPayload(['amount' => 0])),
            'amount',
            Payment::ERROR_AMOUNT_INVALID,
        );
    }

    #[Test]
    public function it_rejects_negative_amount(): void
    {
        $this->assertDomainError(
            fn () => Payment::fromArray($this->validPayload(['amount' => -1])),
            'amount',
            Payment::ERROR_AMOUNT_INVALID,
        );
    }

    #[Test]
    public function it_rejects_missing_amount(): void
    {
        $this->assertDomainError(
            fn () => Payment::fromArray(['paymentType' => 1]),
            'amount',
            Payment::ERROR_AMOUNT_INVALID,
        );
    }

    #[Test]
    public function it_rejects_amount_above_max(): void
    {
        $this->assertDomainError(
            fn () => Payment::fromArray($this->validPayload(['amount' => 1_000_000_000])),
            'amount',
            Payment::ERROR_AMOUNT_TOO_LARGE,
        );
    }

    #[Test]
    public function it_rejects_invalid_payment_type(): void
    {
        $this->assertDomainError(
            fn () => Payment::fromArray($this->validPayload(['paymentType' => 7])),
            'paymentType',
            Payment::ERROR_PAYMENT_TYPE_INVALID,
        );
    }

    #[Test]
    public function it_rejects_missing_payment_type(): void
    {
        $this->assertDomainError(
            fn () => Payment::fromArray(['amount' => 100]),
            'paymentType',
            Payment::ERROR_PAYMENT_TYPE_INVALID,
        );
    }

    #[Test]
    public function it_collects_multiple_errors_at_once(): void
    {
        $this->assertDomainHasErrors(
            fn () => Payment::fromArray(['amount' => -1, 'paymentType' => 99]),
            ['amount', 'paymentType'],
        );
    }

    #[Test]
    public function it_is_immutable(): void
    {
        $payment = Payment::fromArray($this->validPayload());

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('readonly');

        /** @phpstan-ignore-next-line — intentionally violating immutability */
        $payment->amount = 0.0;
    }
}
