<?php

namespace App\Modules\Integration\Xero\Repository;

use App\Shared\Repository\AbstractRepository;
use App\Modules\Integration\Xero\Domain\XeroConnection;

class XeroConnectionRepository extends AbstractRepository
{
    public function __construct(XeroConnection $model)
    {
        parent::__construct($model);
    }

    public function findByTenantId(string $tenantId): ?XeroConnection
    {
        return $this->model->where('tenant_id', $tenantId)->first();
    }

    public function findActiveByOrganizationId(int $organizationId): ?XeroConnection
    {
        return $this->model
            ->where('organization_id', $organizationId)
            ->where('active', true)
            ->first();
    }
}