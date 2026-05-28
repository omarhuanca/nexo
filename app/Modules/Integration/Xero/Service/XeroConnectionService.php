<?php

namespace App\Modules\Integration\Xero\Service;

use App\Modules\Integration\Xero\Domain\XeroConnection;
use App\Modules\Integration\Xero\Repository\XeroConnectionRepository;

class XeroConnectionService
{
    public function __construct(private readonly XeroConnectionRepository $repository) {}
    public function saveOrUpdate(array $tokens, array $connection) : XeroConnection
    {
        $existing = $this->repository->findByTenantId($connection['tenantId']);

        if($existing){
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
}