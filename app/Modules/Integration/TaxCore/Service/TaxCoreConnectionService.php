<?php

namespace App\Modules\Integration\TaxCore\Service;

use App\Events\TaxCore\TaxCoreCertUploaded;
use App\Modules\Integration\TaxCore\Domain\TaxCoreConnection;
use App\Modules\Integration\TaxCore\Repository\TaxCoreConnectionRepository;

class TaxCoreConnectionService
{
    public function __construct(
        private readonly TaxCoreConnectionRepository $repository,
    ) {}

    public function connectAgent(int $organizationId, string $environment): TaxCoreConnection
    {
        $connection = new TaxCoreConnection();
        $connection->setOrganizationId($organizationId);
        $connection->setEnvironment($environment);
        $connection->setActive(true);

        $this->repository->save($connection);

        event(new TaxCoreCertUploaded(
            $organizationId,
            $connection->getId(),
            $environment,
            $vsdcUrl,
        ));

        return $connection;
    }

    public function findActiveByOrganization(int $organizationId): TaxCoreConnection
    {
        return $this->repository->findActiveByOrganizationId($organizationId);
    }
}
