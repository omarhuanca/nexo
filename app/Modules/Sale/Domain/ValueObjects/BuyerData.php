<?php

namespace App\Modules\Sale\Domain\ValueObjects;

use App\Shared\Exceptions\DomainValidationException;

/**
 * Immutable representation of the buyer (customer) of a sale.
 *
 * Encapsulates the domain rules that govern buyer identification:
 *  - name must be a non-empty, trimmed string with a maximum length
 *  - id (tax identification) is optional; when present must be 8-20 digits
 *
 * Instances are created exclusively through {@see fromArray()}, which validates
 * the input and throws {@see DomainValidationException} on any rule violation.
 */
final class BuyerData
{
    public const ERROR_NAME_EMPTY = 'The buyer name cannot be empty.';
    public const ERROR_NAME_TOO_LONG = 'The buyer name cannot exceed 255 characters.';
    public const ERROR_ID_INVALID = 'The buyer ID must contain only digits (8-20 characters).';

    public const MAX_NAME_LENGTH = 255;

    private function __construct(
        public readonly string $name,
        public readonly ?string $id,
    ) {
    }

    /**
     * Build a BuyerData from the raw "buyer" section of a sale payload.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws DomainValidationException when any domain rule fails.
     */
    public static function fromArray(array $data): self
    {
        $errors = [];

        $name = isset($data['name']) ? trim((string) $data['name']) : '';
        $id = array_key_exists('id', $data) && $data['id'] !== null
            ? trim((string) $data['id'])
            : null;

        if ($name === '') {
            $errors['name'][] = self::ERROR_NAME_EMPTY;
        } elseif (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            $errors['name'][] = self::ERROR_NAME_TOO_LONG;
        }

        if ($id !== null && $id !== '' && ! preg_match('/^\d{8,20}$/', $id)) {
            $errors['id'][] = self::ERROR_ID_INVALID;
        }

        if ($errors !== []) {
            throw new DomainValidationException('Invalid buyer data.', $errors);
        }

        return new self($name, $id === '' ? null : $id);
    }
}
