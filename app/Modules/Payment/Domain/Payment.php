<?php

namespace App\Modules\Payment\Domain;

use App\Modules\Sale\Domain\Sale;
use App\Shared\Domain\BaseEntity;
use App\Shared\Exceptions\DomainValidationException;
use App\Shared\Traits\GettersAndSetters;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Payment snapshot entity.
 *
 * Represents a single payment applied to a sale invoice. This is a
 * SNAPSHOT (per-sale) entity, separate from the immutable
 * {@see \App\Modules\Sale\Domain\ValueObjects\Payment} value object that
 * lives inside a Sale's payload.
 *
 * Like LineItem, it has no organization_id (transitively through sale)
 * and no active flag (it is an immutable fiscal record).
 *
 * Domain rules (kept identical to the legacy Payment value object):
 *  - amount must be positive and bounded
 *  - payment_type must be one of the allowed values (0..6)
 *
 * Instances are created through {@see at()} which validates the input
 * and throws {@see DomainValidationException} on any rule violation.
 */
class Payment extends BaseEntity
{
    use HasFactory, GettersAndSetters;

    public const PAYMENT_TYPE_OTHER = 0;
    public const PAYMENT_TYPE_CASH = 1;
    public const PAYMENT_TYPE_CARD = 2;
    public const PAYMENT_TYPE_CHECK = 3;
    public const PAYMENT_TYPE_WIRE_TRANSFER = 4;
    public const PAYMENT_TYPE_VOUCHER = 5;
    public const PAYMENT_TYPE_MOBILE_MONEY = 6;

    public const VALID_PAYMENT_TYPES = [
        self::PAYMENT_TYPE_OTHER,
        self::PAYMENT_TYPE_CASH,
        self::PAYMENT_TYPE_CARD,
        self::PAYMENT_TYPE_CHECK,
        self::PAYMENT_TYPE_WIRE_TRANSFER,
        self::PAYMENT_TYPE_VOUCHER,
        self::PAYMENT_TYPE_MOBILE_MONEY,
    ];

    public const MAX_AMOUNT = 999999999.99;

    public const ERROR_AMOUNT_INVALID = 'The payment amount must be greater than zero.';
    public const ERROR_AMOUNT_TOO_LARGE = 'The payment amount cannot exceed 999999999.99.';
    public const ERROR_PAYMENT_TYPE_INVALID = 'The payment type must be one of: 0, 1, 2, 3, 4, 5, 6.';

    protected $table = 'payments';

    protected $fillable = [
        'sale_id',
        'amount',
        'payment_type',
        'sequence',
    ];

    protected $casts = [
        'amount' => 'float',
        'payment_type' => 'integer',
        'sequence' => 'integer',
    ];

    protected static function newFactory()
    {
        return \Database\Factories\PaymentFactory::new();
    }

    /**
     * Build a fully-validated Payment from raw input.
     *
     * @throws DomainValidationException when any domain rule fails.
     */
    public static function at(Sale $sale, float $amount, int $paymentType, int $sequence = 0): self
    {
        $errors = [];

        if ($amount <= 0) {
            $errors['amount'][] = self::ERROR_AMOUNT_INVALID;
        } elseif ($amount > self::MAX_AMOUNT) {
            $errors['amount'][] = self::ERROR_AMOUNT_TOO_LARGE;
        }

        if (! in_array($paymentType, self::VALID_PAYMENT_TYPES, true)) {
            $errors['payment_type'][] = self::ERROR_PAYMENT_TYPE_INVALID;
        }

        if ($errors !== []) {
            throw new DomainValidationException('Invalid payment data.', $errors);
        }

        $payment = new self();
        $payment->sale_id = $sale->getId();
        $payment->amount = $amount;
        $payment->payment_type = $paymentType;
        $payment->sequence = $sequence;

        return $payment;
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * Returns a human-readable name for the payment type.
     */
    public function paymentTypeName(): string
    {
        return match ($this->payment_type) {
            self::PAYMENT_TYPE_OTHER => 'Other',
            self::PAYMENT_TYPE_CASH => 'Cash',
            self::PAYMENT_TYPE_CARD => 'Card',
            self::PAYMENT_TYPE_CHECK => 'Check',
            self::PAYMENT_TYPE_WIRE_TRANSFER => 'Wire Transfer',
            self::PAYMENT_TYPE_VOUCHER => 'Voucher',
            self::PAYMENT_TYPE_MOBILE_MONEY => 'Mobile Money',
            default => 'Unknown',
        };
    }

    /**
     * Returns a short display label for the payment.
     */
    public function displayLabel(): string
    {
        return sprintf(
            '%s (%s %s)',
            $this->paymentTypeName(),
            number_format($this->amount, 2),
            $this->sale?->organization?->tax_id ?? ''
        );
    }
}
