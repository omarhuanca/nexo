<?php

namespace App\Modules\Organization\Domain;

use App\Modules\Connector\Domain\Connector;
use App\Modules\IntegrationEvent\Domain\IntegrationEvent;
use App\Modules\Product\Domain\Product;
use App\Shared\Domain\BaseEntity;
use App\Shared\Exceptions\DomainValidationException;
use App\Shared\Helpers\Cleaner;
use App\Shared\Traits\GettersAndSetters;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends BaseEntity
{
    use HasFactory, GettersAndSetters;
    protected $table = 'organizations';
    protected $fillable = [
        'name',
        'tax_id',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public static $ERROR_NAME_EMPTY = "The organization name cannot be empty.";
    public static $ERROR_MIN_NAME_LENGTH = "The organization name must be at least 3 characters long.";
    public static $ERROR_MAX_NAME_LENGTH = "The organization name cannot exceed 255 characters.";
    public static $ERROR_ACTIVE_INVALID = "The 'active' field must be a boolean value (true or false).";
    public static $ERROR_TAX_ID_EMPTY = "The organization's tax ID cannot be empty.";
    public static $ERROR_MIN_TAX_ID_LENGTH = "The organization's tax ID must be at least 11 characters long.";
    public static $ERROR_MAX_TAX_ID_LENGTH = "The organization's tax ID cannot exceed 20 characters.";

    // ANALIZAR SI ESTO ES NECESARIO
    public static $ERROR_INVALID_TAX_ID_FORMAT = "The tax ID format is invalid. It should contain only digits and optionally hyphens.";

    protected static function newFactory()
    {
        return \Database\Factories\OrganizationFactory::new();
    }

    public static function at(string $name, string $taxId, bool $active): self
    {
        $name = Cleaner::cleanString($name);
        $taxId = Cleaner::cleanString($taxId);

        $errors = [];

        if (empty($name)) $errors['name'][] = self::$ERROR_NAME_EMPTY;
        elseif (strlen($name) < 3) $errors['name'][] = self::$ERROR_MIN_NAME_LENGTH;
        elseif (strlen($name) > 255) $errors['name'][] = self::$ERROR_MAX_NAME_LENGTH;

        if (!is_bool($active)) $errors['active'][] = self::$ERROR_ACTIVE_INVALID;

        if (empty($taxId)) $errors['tax_id'][] = self::$ERROR_TAX_ID_EMPTY;
        elseif (strlen($taxId) < 3) $errors['tax_id'][] = self::$ERROR_MIN_TAX_ID_LENGTH;
        elseif (strlen($taxId) > 20) $errors['tax_id'][] = self::$ERROR_MAX_TAX_ID_LENGTH;
        elseif (!preg_match('/^[\d-]+$/', $taxId)) $errors['tax_id'][] = self::$ERROR_INVALID_TAX_ID_FORMAT;

        if (!empty($errors)) throw new DomainValidationException("Validation failed.", $errors);


        return new self([
            'name' => trim($name),
            'tax_id' => trim($taxId),
            'active' => $active,
        ]);
    }

    public function connectors(): HasMany
    {
        return $this->hasMany(Connector::class, 'organization_id');
    }

    public function integrationEvents(): HasMany
    {
        return $this->hasMany(IntegrationEvent::class, 'organization_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'organization_id');
    }
}
