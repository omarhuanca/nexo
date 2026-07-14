<?php

namespace App\Modules\Sale\Domain\ValueObjects;

use App\Shared\Exceptions\DomainValidationException;

/**
 * Immutable representation of a single payment applied to a sale invoice.
 *
 * Encapsulates the domain rules that govern a payment:
 *  - amount must be positive and bounded
 *  - paymentType must be one of the allowed values (0..6)
 *
 * Instances are created exclusively through {@see fromArray()}, which validates
 * the input and throws {@see DomainValidationException} on any rule violation.
 */
final class Payment
{
    public const ERROR_AMOUNT_INVALID = 'The payment amount must be greater than zero.';
    public const ERROR_AMOUNT_TOO_LARGE = 'The payment amount cannot exceed 999999999.99.';
    public const ERROR_PAYMENT_TYPE_INVALID = 'The payment type must be one of: 0, 1, 2, 3, 4, 5, 6.';

    public const MAX_AMOUNT = 999999999.99;

    public const VALID_PAYMENT_TYPES = [0, 1, 2, 3, 4, 5, 6];

    private function __construct(
        public readonly float $amount,
        public readonly int $paymentType,
    ) {
    }

    /**
     * Build a Payment from the raw "payment[]" section of a sale payload.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws DomainValidationException when any domain rule fails.
     */
    public static function fromArray(array $data): self
    {
        $errors = [];

        $amount = isset($data['amount']) ? (float) $data['amount'] : 0.0;
        $paymentType = isset($data['paymentType']) ? (int) $data['paymentType'] : -1;

        if ($amount <= 0) {
            $errors['amount'][] = self::ERROR_AMOUNT_INVALID;
        } elseif ($amount > self::MAX_AMOUNT) {
            $errors['amount'][] = self::ERROR_AMOUNT_TOO_LARGE;
        }

        if (! in_array($paymentType, self::VALID_PAYMENT_TYPES, true)) {
            $errors['paymentType'][] = self::ERROR_PAYMENT_TYPE_INVALID;
        }

        if ($errors !== []) {
            throw new DomainValidationException('Invalid payment data.', $errors);
        }

        return new self($amount, $paymentType);
    }
}
