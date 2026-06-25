<?php

namespace App\Modules\Sale\Domain;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

final class ListSalesCriteria
{
    public const DEFAULT_STATUS = 'completed';

    public const DEFAULT_PER_PAGE = 50;

    public const MAX_PER_PAGE = 100;

    public function __construct(
        public readonly string $status = self::DEFAULT_STATUS,
        public readonly ?int $invoiceType = null,
        public readonly ?int $transactionType = null,
        public readonly ?string $fiscalNumber = null,
        public readonly ?CarbonImmutable $dateFrom = null,
        public readonly ?CarbonImmutable $dateTo = null,
        public readonly int $page = 1,
        public readonly int $perPage = self::DEFAULT_PER_PAGE,
    ) {
        if ($page < 1) {
            throw new InvalidArgumentException('page must be >= 1.');
        }
        if ($perPage < 1 || $perPage > self::MAX_PER_PAGE) {
            throw new InvalidArgumentException(
                'perPage must be between 1 and '.self::MAX_PER_PAGE.'.'
            );
        }
        if ($dateFrom && $dateTo && $dateTo->lt($dateFrom)) {
            throw new InvalidArgumentException('dateTo must be on or after dateFrom.');
        }
    }

    /**
     * Build a criteria object from a validated FormRequest payload.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            status: $data['status'] ?? self::DEFAULT_STATUS,
            invoiceType: isset($data['invoiceType']) ? (int) $data['invoiceType'] : null,
            transactionType: isset($data['transactionType']) ? (int) $data['transactionType'] : null,
            fiscalNumber: $data['fiscalNumber'] ?? null,
            dateFrom: ! empty($data['dateFrom']) ? CarbonImmutable::parse($data['dateFrom'])->startOfDay() : null,
            dateTo: ! empty($data['dateTo']) ? CarbonImmutable::parse($data['dateTo'])->endOfDay() : null,
            page: (int) ($data['page'] ?? 1),
            perPage: (int) ($data['pageSize'] ?? self::DEFAULT_PER_PAGE),
        );
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
