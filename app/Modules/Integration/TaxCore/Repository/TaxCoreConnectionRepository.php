<?php

namespace App\Modules\Integration\TaxCore\Repository;

use App\Modules\Integration\TaxCore\Domain\TaxCoreConnection;
use App\Shared\Exceptions\NotFoundException;
use App\Shared\Repository\AbstractRepository;

class TaxCoreConnectionRepository extends AbstractRepository
{
    public function __construct(TaxCoreConnection $model)
    {
        parent::__construct($model);
    }

    public function findByOrganizationId(int $organizationId): ?TaxCoreConnection
    {
        return $this->model->where('organization_id', $organizationId)->first();
    }

    public function findActiveByOrganizationId(int $organizationId): TaxCoreConnection
    {
        $connection = $this->model
            ->where('organization_id', $organizationId)
            ->where('active', true)
            ->first();

        if (!$connection) {
            throw new NotFoundException(
                "No active TaxCore connection found for organization {$organizationId}."
            );
        }

        return $connection;
    }
}
