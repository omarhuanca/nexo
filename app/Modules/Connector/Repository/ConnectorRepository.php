<?php
namespace App\Modules\Connector\Repository;

use App\Modules\Connector\Domain\Connector;
use App\Shared\Repository\AbstractRepository;
use Illuminate\Pagination\LengthAwarePaginator;

class ConnectorRepository extends AbstractRepository
{
    public function __construct(Connector $connector)
    {
        parent::__construct($connector);
    }

    public function paginateByOrganization(int $organizationId, int $perPage = 10): LengthAwarePaginator
    {
        return $this->model
            ->where('organization_id', $organizationId)
            ->paginate($perPage);
    }

    public function findByTokenHash(string $hash): ?Connector
    {
        return $this->model->where('token', $hash)->first();
    }

    public function findFirstActiveByOrganization(int $organizationId): ?Connector
    {
        return $this->model
            ->where('organization_id', $organizationId)
            ->where('active', true)
            ->orderBy('id')
            ->first();
    }

    public function existsByNameInOrganization(string $name, int $organizationId, ?int $excludeId = null): bool
    {
        return $this->model
        ->whereRaw('LOWER(name) = LOWER(?)', [trim($name)])
        ->where('organization_id', $organizationId)
        ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
        ->exists();
    }
}
