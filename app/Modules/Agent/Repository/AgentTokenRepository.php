<?php

namespace App\Modules\Agent\Repository;

use App\Modules\Agent\Domain\AgentToken;
use App\Shared\Repository\AbstractRepository;

class AgentTokenRepository extends AbstractRepository
{
    public function __construct(AgentToken $model)
    {
        parent::__construct($model);
    }

    public function findByTokenHash(string $hash): ?AgentToken
    {
        return $this->model
            ->where('token_hash', $hash)
            ->where('active', true)
            ->first();
    }

    public function findActiveByOrganization(int $organizationId): ?AgentToken
    {
        return $this->model
            ->where('organization_id', $organizationId)
            ->where('active', true)
            ->latest('last_seen_at')
            ->first();
    }
}
