<?php

namespace App\Modules\Sale\Domain\ValueObjects;

use App\Shared\Exceptions\DomainValidationException;

/**
 * Immutable representation of a single line item of a sale invoice.
 *
 * Encapsulates the domain rules that govern an invoice line:
 *  - code, name and accountCode must be non-empty (trimmed)
 *  - quantity must be positive and bounded
 *  - unitPrice and totalAmount must be non-negative and bounded
 *  - quantity * unitPrice must approximately equal totalAmount (tolerance 0.01)
 *  - labels must contain at least one valid fiscal label (A-H)
 *  - gtin is optional; when present must be 8-14 digits
 *
 * Instances are created exclusively through {@see fromArray()}, which validates
 * the input and throws {@see DomainValidationException} on any rule violation.
 */
final class LineItem
{
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

    public const MAX_CODE_LENGTH = 30;
    public const MAX_NAME_LENGTH = 2048;
    public const MAX_QUANTITY = 999999;
    public const MAX_MONETARY_AMOUNT = 999999999.99;
    public const MAX_ACCOUNT_CODE_LENGTH = 10;

    public const VALID_LABELS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

    private const TOLERANCE = 0.01;

    private function __construct(
        public readonly string $code,
        public readonly string $name,
        public readonly float $quantity,
        public readonly float $unitPrice,
        public readonly float $totalAmount,
        public readonly array $labels,
        public readonly string $accountCode,
        public readonly ?string $gtin,
    ) {
    }

    /**
     * Build a LineItem from the raw "items[]" section of a sale payload.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws DomainValidationException when any domain rule fails.
     */
    public static function fromArray(array $data): self
    {
        $errors = [];

        $code = isset($data['code']) ? trim((string) $data['code']) : '';
        $name = isset($data['name']) ? trim((string) $data['name']) : '';
        $quantity = isset($data['quantity']) ? (float) $data['quantity'] : 0.0;
        $unitPrice = isset($data['unitPrice']) ? (float) $data['unitPrice'] : 0.0;
        $totalAmount = isset($data['totalAmount']) ? (float) $data['totalAmount'] : 0.0;
        $labels = $data['labels'] ?? [];
        $accountCode = isset($data['accountCode']) ? trim((string) $data['accountCode']) : '';
        $gtin = array_key_exists('gtin', $data) && $data['gtin'] !== null
            ? trim((string) $data['gtin'])
            : null;

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

        return new self(
            $code,
            $name,
            $quantity,
            $unitPrice,
            $totalAmount,
            array_values(array_map('strval', $labels)),
            $accountCode,
            $gtin === '' ? null : $gtin,
        );
    }
}
