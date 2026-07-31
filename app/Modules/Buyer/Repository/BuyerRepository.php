<?php

namespace App\Modules\Buyer\Repository;

use App\Modules\Buyer\Domain\Buyer;
use App\Shared\Repository\AbstractRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class BuyerRepository extends AbstractRepository
{
    public function __construct(Buyer $model)
    {
        parent::__construct($model);
    }

    public function findByTaxIdForOrganization(string $taxId, int $organizationId): ?Buyer
    {
        return $this->model
            ->where('organization_id', $organizationId)
            ->where('tax_id', $taxId)
            ->first();
    }

    public function paginateByOrganization(int $organizationId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function existsByTaxIdInOrganization(string $taxId, int $organizationId, ?int $excludeId = null): bool
    {
        $query = $this->model
            ->where('organization_id', $organizationId)
            ->where('tax_id', $taxId);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
