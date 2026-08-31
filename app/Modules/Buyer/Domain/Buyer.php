<?php

namespace App\Modules\Buyer\Domain;

use App\Modules\Organization\Domain\Organization;
use App\Shared\Domain\BaseEntity;
use App\Shared\Exceptions\DomainValidationException;
use App\Shared\Traits\GettersAndSetters;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Buyer catalog entity.
 *
 * Represents a real-world buyer (customer) belonging to an organization.
 * This is a MUTABLE catalog entry, reusable across multiple sales.
 *
 * Domain rules:
 *  - name is required, non-empty, max 255 chars
 *  - document_number is optional; when present must be 8-20 digits
 *
 * Instances are created through {@see at()} which validates the input
 * and throws {@see DomainValidationException} on any rule violation.
 */
class Buyer extends BaseEntity
{
    use HasFactory, GettersAndSetters;

    public const MAX_NAME_LENGTH = 255;
    public const DOCUMENT_NUMBER_MIN_LENGTH = 8;
    public const DOCUMENT_NUMBER_MAX_LENGTH = 20;

    public const DOCUMENT_NUMBER_PATTERN = '/^\d{8,20}$/';

    public const ERROR_NAME_EMPTY = 'The buyer name cannot be empty.';
    public const ERROR_NAME_TOO_LONG = 'The buyer name cannot exceed 255 characters.';
    public const ERROR_DOCUMENT_NUMBER_INVALID = 'The buyer document number must contain only digits (8-20 characters).';

    protected $table = 'buyers';

    protected $fillable = [
        'organization_id',
        'name',
        'document_number',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected static function newFactory()
    {
        return \Database\Factories\BuyerFactory::new();
    }

    /**
     * Build a fully-validated Buyer from raw input.
     *
     * @throws DomainValidationException when any domain rule fails.
     */
    public static function at(Organization $organization, string $name, ?string $documentNumber = null): self
    {
        $errors = [];

        $name = trim($name);
        $documentNumberRaw = $documentNumber;
        $documentNumber = ($documentNumber === null || trim($documentNumber) === '') ? null : trim($documentNumber);

        if ($name === '') {
            $errors['name'][] = self::ERROR_NAME_EMPTY;
        } elseif (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            $errors['name'][] = self::ERROR_NAME_TOO_LONG;
        }

        if ($documentNumber !== null && ! preg_match(self::DOCUMENT_NUMBER_PATTERN, $documentNumber)) {
            $errors['document_number'][] = self::ERROR_DOCUMENT_NUMBER_INVALID;
        }

        if ($errors !== []) {
            throw new DomainValidationException('Invalid buyer data.', $errors);
        }

        unset($documentNumberRaw);

        $buyer = new self();
        $buyer->organization_id = $organization->getId();
        $buyer->name = $name;
        $buyer->document_number = $documentNumber;
        $buyer->active = true;

        return $buyer;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Returns true when both this buyer and the given document_number are non-null
     * and equal. Useful for matching incoming sale payloads against the
     * catalog without doing string juggling at the call site.
     */
    public function matchesDocumentNumber(?string $documentNumber): bool
    {
        if ($this->document_number === null || $documentNumber === null) {
            return false;
        }

        return $this->document_number === $documentNumber;
    }

    public function displayLabel(): string
    {
        return $this->document_number
            ? "{$this->name} ({$this->document_number})"
            : $this->name;
    }
}
