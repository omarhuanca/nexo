<?php

namespace App\Modules\Integration\Xero\Service;

use App\Modules\Integration\Xero\Domain\XeroConnection;
use App\Modules\Integration\Xero\Repository\XeroConnectionRepository;
use App\Shared\Exceptions\NotFoundException;

class XeroConnectionService
{
    public function __construct(
        private readonly XeroConnectionRepository $repository
        ) {}
    public function saveOrUpdate(array $tokens, array $connection, int $organizationId) : XeroConnection
    {
        $existing = $this->repository->findByTenantId($connection['tenantId']);

        if($existing){
            $existing->setOrganizationId($organizationId);
            $existing->setTenantName($connection['tenantName']);
            $existing->setTenantType($connection['tenantType']);

            $existing->setAccessToken($tokens['access_token']);
            $existing->setRefreshToken($tokens['refresh_token']);

            $existing->setExpiresAt(now()->addSeconds($tokens['expires_in']));
            $existing->setScopes($tokens['scope'] ?? "");

            $this->repository->save($existing);

            return $existing;
        }

        $newConnection = new XeroConnection();

        $newConnection->setOrganizationId($organizationId);
        $newConnection->setTenantId($connection['tenantId']);
        $newConnection->setTenantName($connection['tenantName']);
        $newConnection->setTenantType($connection['tenantType']);

        $newConnection->setAccessToken($tokens['access_token']);
        $newConnection->setRefreshToken($tokens['refresh_token']);
        $newConnection->setExpiresAt(now()->addSeconds($tokens['expires_in']));
        $newConnection->setScopes($tokens['scope'] ?? "");
        
        return $this->repository->saveReturn($newConnection);
    }

    public function findById(int $id): XeroConnection
    {
        return $this->repository->findById($id);
    }

    public function findByTenantId(string $id): XeroConnection
    {
        return $this->repository->findByTenantId($id);
    }

    public function findActiveByOrganization(int $organizationId): XeroConnection
    {
        $connection = $this->repository->findActiveByOrganizationId($organizationId);

        if (!$connection) throw new NotFoundException("No active Xero connection found for this organization.");
        
        return $connection;
    }
}