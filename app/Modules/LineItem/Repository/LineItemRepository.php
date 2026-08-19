<?php

namespace App\Modules\LineItem\Repository;

use App\Modules\LineItem\Domain\LineItem;
use App\Shared\Repository\AbstractRepository;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class LineItemRepository extends AbstractRepository
{
    public function __construct(LineItem $model)
    {
        parent::__construct($model);
    }

    public function findBySaleId(int $saleId): Collection
    {
        return $this->model
            ->where('sale_id', $saleId)
            ->orderBy('id')
            ->get();
    }

    public function paginateBySale(int $saleId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->where('sale_id', $saleId)
            ->orderBy('id')
            ->paginate($perPage);
    }

    public function findByCodeForOrganization(string $code, int $organizationId): Collection
    {
        return $this->model
            ->whereHas('sale', function ($query) use ($organizationId) {
                $query->where('organization_id', $organizationId);
            })
            ->where('code', $code)
            ->get();
    }
}
