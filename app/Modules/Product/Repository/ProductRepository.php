<?php

namespace App\Modules\Product\Repository;

use App\Modules\Product\Domain\Product;
use App\Shared\Repository\AbstractRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class ProductRepository extends AbstractRepository
{
    public function __construct(Product $model)
    {
        parent::__construct($model);
    }

    public function paginateByOrganization(
        int $organizationId,
        int $perPage = 15,
    ): LengthAwarePaginator {
        return $this->model
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function existsByCodeInOrganization(
        string $code,
        int $organizationId,
    ): bool {
        return $this->model
            ->where('organization_id', $organizationId)
            ->where('code', $code)
            ->exists();
    }
}
