<?php

namespace App\Modules\Sale\Repository;

use App\Modules\Sale\Domain\Sale;
use App\Shared\Exceptions\NotFoundException;
use App\Shared\Repository\AbstractRepository;

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

    public function findByIdForOrganization(int $id, int $organizationId): Sale
    {
        $sale = $this->model
            ->where('id', $id)
            ->where('organization_id', $organizationId)
            ->first();

        if (!$sale) {
            throw new NotFoundException("Sale not found.");
        }

        return $sale;
    }
}
