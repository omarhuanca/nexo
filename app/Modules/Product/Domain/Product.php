<?php

namespace App\Modules\Product\Domain;

use App\Modules\Organization\Domain\Organization;
use App\Shared\Domain\BaseEntity;
use App\Shared\Exceptions\DomainValidationException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends BaseEntity
{
	use HasFactory;

	public const MAX_CODE_LENGTH = 30;
	public const MAX_NAME_LENGTH = 255;
	public const MAX_DESCRIPTION_LENGTH = 2048;
	public const MAX_MONETARY_AMOUNT = 999999999.99;

	public const ERROR_CODE_EMPTY = 'The product code cannot be empty.';
	public const ERROR_CODE_TOO_LONG = 'The product code cannot exceed 30 characters.';
	public const ERROR_NAME_EMPTY = 'The product name cannot be empty.';
	public const ERROR_NAME_TOO_LONG = 'The product name cannot exceed 255 characters.';
	public const ERROR_DESCRIPTION_TOO_LONG = 'The product description cannot exceed 2048 characters.';
	public const ERROR_SALE_PRICE_NEGATIVE = 'The product sale price cannot be negative.';
	public const ERROR_SALE_PRICE_TOO_LARGE = 'The product sale price cannot exceed 999999999.99.';
	public const ERROR_COST_PRICE_NEGATIVE = 'The product cost price cannot be negative.';
	public const ERROR_COST_PRICE_TOO_LARGE = 'The product cost price cannot exceed 999999999.99.';

	protected $table = 'products';

	protected $fillable = [
		'organization_id',
		'code',
		'name',
		'description',
		'sale_price',
		'cost_price',
	];

	protected $casts = [
		'sale_price' => 'float',
		'cost_price' => 'float',
	];

	public static function at(
		Organization $organization,
		string $code,
		string $name,
		string $description,
		float $salePrice,
		float $costPrice,
	): self {
		$errors = [];

		$code = trim($code);
		$name = trim($name);
		$description = trim($description);

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

		if (mb_strlen($description) > self::MAX_DESCRIPTION_LENGTH) {
			$errors['description'][] = self::ERROR_DESCRIPTION_TOO_LONG;
		}

		if ($salePrice < 0) {
			$errors['salePrice'][] = self::ERROR_SALE_PRICE_NEGATIVE;
		} elseif ($salePrice > self::MAX_MONETARY_AMOUNT) {
			$errors['salePrice'][] = self::ERROR_SALE_PRICE_TOO_LARGE;
		}

		if ($costPrice < 0) {
			$errors['costPrice'][] = self::ERROR_COST_PRICE_NEGATIVE;
		} elseif ($costPrice > self::MAX_MONETARY_AMOUNT) {
			$errors['costPrice'][] = self::ERROR_COST_PRICE_TOO_LARGE;
		}

		if ($errors !== []) {
			throw new DomainValidationException('Invalid product data.', $errors);
		}

		return new self([
			'organization_id' => $organization->id,
			'code' => $code,
			'name' => $name,
			'description' => $description,
			'sale_price' => $salePrice,
			'cost_price' => $costPrice,
		]);
	}

	public function organization(): BelongsTo
	{
		return $this->belongsTo(Organization::class);
	}
}
