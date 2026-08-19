<?php

namespace App\Modules\LineItem\Domain;

use App\Modules\Sale\Domain\Sale;
use App\Shared\Domain\BaseEntity;
use App\Shared\Exceptions\DomainValidationException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LineItem snapshot entity.
 *
 * Represents a single line item of a sale invoice. This is a
 * SNAPSHOT (per-sale) entity, separate from the immutable
 * {@see \App\Modules\Sale\Domain\ValueObjects\LineItem} value object
 * that lives inside a Sale's payload.
 *
 * Like Payment, it has no organization_id (transitively through sale)
 * and no active flag (it is an immutable fiscal record).
 *
 * Domain rules (kept identical to the legacy LineItem value object):
 *  - code, name and accountCode must be non-empty (trimmed)
 *  - quantity must be positive and bounded
 *  - unit_price and total_amount must be non-negative and bounded
 *  - quantity * unit_price must approximately equal total_amount (tolerance 0.01)
 *  - labels must contain at least one valid fiscal label (A-H)
 *  - gtin is optional; when present must be 8-14 digits
 *
 * Instances are created through {@see at()} which validates the input
 * and throws {@see DomainValidationException} on any rule violation.
 */
class LineItem extends BaseEntity
{
    use HasFactory;

    public const MAX_CODE_LENGTH = 30;
    public const MAX_NAME_LENGTH = 2048;
    public const MAX_QUANTITY = 999999;
    public const MAX_MONETARY_AMOUNT = 999999999.99;
    public const MAX_ACCOUNT_CODE_LENGTH = 10;

    public const VALID_LABELS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

    private const TOLERANCE = 0.01;

    public const ERROR_CODE_EMPTY = 'The item code cannot be empty.';
    public const ERROR_CODE_TOO_LONG = 'The item code cannot exceed 30 characters.';
    public const ERROR_NAME_EMPTY = 'The item name cannot be empty.';
    public const ERROR_NAME_TOO_LONG = 'The item name cannot exceed 2048 characters.';
    public const ERROR_QUANTITY_INVALID = 'The item quantity must be greater than zero.';
    public const ERROR_QUANTITY_TOO_LARGE = 'The item quantity cannot exceed 999999.';
    public const ERROR_UNIT_PRICE_NEGATIVE = 'The item unit price cannot be negative.';
    public const ERROR_UNIT_PRICE_TOO_LARGE = 'The item unit price cannot exceed 999999999.99.';
    public const ERROR_TOTAL_AMOUNT_NEGATIVE = 'The item total amount cannot be negative.';
    public const ERROR_TOTAL_AMOUNT_TOO_LARGE = 'The item total amount cannot exceed 999999999.99.';
    public const ERROR_TOTAL_MISMATCH = 'The item total amount does not match quantity × unit price.';
    public const ERROR_LABELS_EMPTY = 'The item must have at least one tax label.';
    public const ERROR_LABEL_INVALID = 'Invalid tax label. Allowed values: A, B, C, D, E, F, G, H.';
    public const ERROR_ACCOUNT_CODE_EMPTY = 'The item account code cannot be empty.';
    public const ERROR_ACCOUNT_CODE_TOO_LONG = 'The item account code cannot exceed 10 characters.';
    public const ERROR_GTIN_INVALID = 'The item GTIN must be 8-14 digits.';

    protected $table = 'line_items';

    protected $fillable = [
        'sale_id',
        'code',
        'name',
        'quantity',
        'unit_price',
        'total_amount',
        'labels',
        'account_code',
        'gtin',
    ];

    protected $casts = [
        'quantity' => 'float',
        'unit_price' => 'float',
        'total_amount' => 'float',
        'labels' => 'array',
    ];

    protected static function newFactory()
    {
        return \Database\Factories\LineItemFactory::new();
    }

    /**
     * Build a fully-validated LineItem from raw input.
     *
     * @param  array<string, mixed>|array<int, string>  $labels
     *
     * @throws DomainValidationException when any domain rule fails.
     */
    public static function at(
        Sale $sale,
        string $code,
        string $name,
        float $quantity,
        float $unitPrice,
        float $totalAmount,
        array $labels,
        string $accountCode,
        ?string $gtin = null,
    ): self {
        $errors = [];

        $code = trim($code);
        $name = trim($name);
        $accountCode = trim($accountCode);
        $gtin = ($gtin === null || trim($gtin) === '') ? null : trim($gtin);

        if ($code === '') {
            $errors['code'][] = self::ERROR_CODE_EMPTY;
        } elseif (mb_strlen($code) > self::MAX_CODE_LENGTH) {
            $errors['code'][] = self::ERROR_CODE_TOO_LONG;
        }

        if ($name === '') {
            $errors['name'][] = self::ERROR_NAME_EMPTY;
        } elseif (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            $errors['name'][] = self::ERROR_NAME_TOO_LONG;
        }

        if ($quantity <= 0) {
            $errors['quantity'][] = self::ERROR_QUANTITY_INVALID;
        } elseif ($quantity > self::MAX_QUANTITY) {
            $errors['quantity'][] = self::ERROR_QUANTITY_TOO_LARGE;
        }

        if ($unitPrice < 0) {
            $errors['unitPrice'][] = self::ERROR_UNIT_PRICE_NEGATIVE;
        } elseif ($unitPrice > self::MAX_MONETARY_AMOUNT) {
            $errors['unitPrice'][] = self::ERROR_UNIT_PRICE_TOO_LARGE;
        }

        if ($totalAmount < 0) {
            $errors['totalAmount'][] = self::ERROR_TOTAL_AMOUNT_NEGATIVE;
        } elseif ($totalAmount > self::MAX_MONETARY_AMOUNT) {
            $errors['totalAmount'][] = self::ERROR_TOTAL_AMOUNT_TOO_LARGE;
        }

        if ($quantity > 0
            && $unitPrice >= 0
            && abs(($quantity * $unitPrice) - $totalAmount) > self::TOLERANCE) {
            $errors['totalAmount'][] = self::ERROR_TOTAL_MISMATCH;
        }

        if (! is_array($labels) || $labels === []) {
            $errors['labels'][] = self::ERROR_LABELS_EMPTY;
        } else {
            foreach ($labels as $label) {
                if (! is_string($label) || ! in_array($label, self::VALID_LABELS, true)) {
                    $errors['labels'][] = self::ERROR_LABEL_INVALID;
                    break;
                }
            }
        }

        if ($accountCode === '') {
            $errors['accountCode'][] = self::ERROR_ACCOUNT_CODE_EMPTY;
        } elseif (mb_strlen($accountCode) > self::MAX_ACCOUNT_CODE_LENGTH) {
            $errors['accountCode'][] = self::ERROR_ACCOUNT_CODE_TOO_LONG;
        }

        if ($gtin !== null && $gtin !== '' && ! preg_match('/^\d{8,14}$/', $gtin)) {
            $errors['gtin'][] = self::ERROR_GTIN_INVALID;
        }

        if ($errors !== []) {
            throw new DomainValidationException('Invalid line item data.', $errors);
        }

        return new self([
            'sale_id' => $sale->id,
            'code' => $code,
            'name' => $name,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_amount' => $totalAmount,
            'labels' => array_values(array_map('strval', $labels)),
            'account_code' => $accountCode,
            'gtin' => $gtin === '' ? null : $gtin,
        ]);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
