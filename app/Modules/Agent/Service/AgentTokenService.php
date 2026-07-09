<?php

namespace App\Modules\Agent\Service;

use App\Modules\Agent\Domain\AgentToken;
use App\Modules\Agent\Repository\AgentTokenRepository;

class AgentTokenService
{
    public function __construct(private readonly AgentTokenRepository $repository) {}

    public function createToken(int $organizationId, string $name = 'nexo-agent'): array
    {
        $plain = bin2hex(random_bytes(32));

        $token = new AgentToken();
        $token->setOrganizationId($organizationId);
        $token->setTokenHash(hash('sha256', $plain));
        $token->setName($name);
        $token->setActive(true);

        $this->repository->save($token);

        return ['token' => $plain, 'agent_token_id' => $token->id];
    }

    public function authenticate(string $plainToken): ?AgentToken
    {
        return $this->repository->findByTokenHash(hash('sha256', $plainToken));
    }

    public function touch(AgentToken $agentToken): void
    {
        $agentToken->setLastSeenAt(now());
        $this->repository->save($agentToken);
    }

    public function isOnline(int $organizationId): bool
    {
        $token = $this->repository->findActiveByOrganization($organizationId);
        if (!$token?->getLastSeenAt()) {
            return false;
        }
        return $token->getLastSeenAt()->gt(now()->subSeconds(30));
    }
}
