<?php

namespace App\Modules\Integration\Repository;

use App\Shared\Repository\AbstractRepository;
use App\Modules\Integration\Domain\Xero\XeroConnection;

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
}