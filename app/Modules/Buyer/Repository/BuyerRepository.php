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

    public function findByDocumentNumberForOrganization(string $documentNumber, int $organizationId): ?Buyer
    {
        return $this->model
            ->where('organization_id', $organizationId)
            ->where('document_number', $documentNumber)
            ->first();
    }

    public function paginateByOrganization(int $organizationId, int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function existsByDocumentNumberInOrganization(string $documentNumber, int $organizationId, ?int $excludeId = null): bool
    {
        $query = $this->model
            ->where('organization_id', $organizationId)
            ->where('document_number', $documentNumber);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
