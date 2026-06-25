<?php

namespace App\Modules\Sale\Repository;

use App\Modules\Sale\Domain\ListSalesCriteria;
use App\Modules\Sale\Domain\Sale;
use App\Shared\Exceptions\NotFoundException;
use App\Shared\Repository\AbstractRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class SaleRepository extends AbstractRepository
{
    public function __construct(Sale $model)
    {
        parent::__construct($model);
    }

    public function findByIdWithLock(int $id): ?Sale
    {
        return $this->model->lockForUpdate()->find($id);
    }

    /**
     * @return Sale[]
     */
    public function findPendingFiscalByOrganization(int $organizationId): array
    {
        return $this->model
            ->where('organization_id', $organizationId)
            ->where('status', 'pending_fiscal')
            ->whereNull('fiscal_number')
            ->orderBy('created_at')
            ->get()
            ->all();
    }

    public function findByIdForOrganization(int $id, int $organizationId): Sale
    {
        $query = $this->model->where('id', $id);

        if ($organizationId !== null) {
            $query->where('organization_id', $organizationId);
        }

        $sale = $query->first();

        if (! $sale) {
            throw new NotFoundException('Sale not found.');
        }

        return $sale;
    }

    public function listForOrganization(?int $organizationId, ListSalesCriteria $criteria): LengthAwarePaginator
    {
        $query = $this->model->where('status', $criteria->status);

        if ($organizationId !== null) {
            $query->where('organization_id', $organizationId);
        }

        if ($criteria->fiscalNumber !== null) {
            $query->where('fiscal_number', $criteria->fiscalNumber);
        }

        if ($criteria->dateFrom !== null) {
            $query->where('created_at', '>=', $criteria->dateFrom);
        }

        if ($criteria->dateTo !== null) {
            $query->where('created_at', '<=', $criteria->dateTo);
        }

        if ($criteria->invoiceType !== null) {
            $query->whereRaw("(payload->>'invoiceType')::int = ?", [$criteria->invoiceType]);
        }

        if ($criteria->transactionType !== null) {
            $query->whereRaw("(payload->>'transactionType')::int = ?", [$criteria->transactionType]);
        }

        return $query
            ->orderByDesc('created_at')
            ->paginate(perPage: $criteria->perPage, page: $criteria->page);
    }
}
