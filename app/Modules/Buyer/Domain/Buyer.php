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
 * This is a MUTABLE catalog entry, separate from the immutable
 * {@see \App\Modules\Sale\Domain\ValueObjects\BuyerData} snapshot that
 * lives inside a Sale's payload.
 *
 * Domain rules (kept identical to the legacy BuyerData value object):
 *  - name is required, non-empty, max 255 chars
 *  - tax_id is optional; when present must be 8-20 digits
 *
 * Instances are created through {@see at()} which validates the input
 * and throws {@see DomainValidationException} on any rule violation.
 */
class Buyer extends BaseEntity
{
    use HasFactory, GettersAndSetters;

    public const MAX_NAME_LENGTH = 255;
    public const TAX_ID_MIN_LENGTH = 8;
    public const TAX_ID_MAX_LENGTH = 20;

    public const TAX_ID_PATTERN = '/^\d{8,20}$/';

    public const ERROR_NAME_EMPTY = 'The buyer name cannot be empty.';
    public const ERROR_NAME_TOO_LONG = 'The buyer name cannot exceed 255 characters.';
    public const ERROR_TAX_ID_INVALID = 'The buyer ID must contain only digits (8-20 characters).';

    protected $table = 'buyers';

    protected $fillable = [
        'organization_id',
        'name',
        'tax_id',
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
    public static function at(Organization $organization, string $name, ?string $taxId = null): self
    {
        $errors = [];

        $name = trim($name);
        $taxIdRaw = $taxId;
        $taxId = ($taxId === null || trim($taxId) === '') ? null : trim($taxId);

        if ($name === '') {
            $errors['name'][] = self::ERROR_NAME_EMPTY;
        } elseif (mb_strlen($name) > self::MAX_NAME_LENGTH) {
            $errors['name'][] = self::ERROR_NAME_TOO_LONG;
        }

        if ($taxId !== null && ! preg_match(self::TAX_ID_PATTERN, $taxId)) {
            $errors['tax_id'][] = self::ERROR_TAX_ID_INVALID;
        }

        if ($errors !== []) {
            throw new DomainValidationException('Invalid buyer data.', $errors);
        }

        unset($taxIdRaw);

        $buyer = new self();
        $buyer->organization_id = $organization->getId();
        $buyer->name = $name;
        $buyer->tax_id = $taxId;
        $buyer->active = true;

        return $buyer;
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Returns true when both this buyer and the given tax_id are non-null
     * and equal. Useful for matching incoming sale payloads against the
     * catalog without doing string juggling at the call site.
     */
    public function matchesTaxId(?string $taxId): bool
    {
        if ($this->tax_id === null || $taxId === null) {
            return false;
        }

        return $this->tax_id === $taxId;
    }

    public function displayLabel(): string
    {
        return $this->tax_id
            ? "{$this->name} ({$this->tax_id})"
            : $this->name;
    }
}
