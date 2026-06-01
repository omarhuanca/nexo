<?php

namespace App\Modules\Connector\Service;

use App\Modules\Connector\Domain\Connector;
use App\Modules\Connector\Repository\ConnectorRepository;
use App\Modules\IntegrationEvent\Repository\IntegrationEventRepository;
use App\Shared\Exceptions\BusinessConflictException;
use Illuminate\Pagination\LengthAwarePaginator;

class ConnectorService
{
    public function __construct(
        private readonly ConnectorRepository $connectorRepository,
        private readonly IntegrationEventRepository $integrationEventRepository
    ) {}

    public function createConnector(int $organizationId, string $name, array $allowedEvents = [], bool $active = true): Connector
    {
        if ($this->connectorRepository->existsByNameInOrganization($name, $organizationId)) {
            throw new BusinessConflictException("A connector with the same name already exists in this organization.");
        }

        $connector = new Connector();
        $connector->setOrganizationId($organizationId);
        $connector->setName($name);
        $connector->setToken(bin2hex(random_bytes(16)));
        $connector->setActive($active);
        $connector->setAllowedEvents(empty($allowedEvents) ? null : $allowedEvents);
        return $this->connectorRepository->saveReturn($connector);
    }

    public function getConnectorById(int $id): Connector
    {
        return $this->connectorRepository->findById($id);
    }

    public function listConnectorsByOrganization(int $organizationId, int $perPage = 10): LengthAwarePaginator
    {
        return $this->connectorRepository->paginateByOrganization($organizationId, $perPage);
    }

    public function updateConnector(int $id, array $data): Connector
    {
        $connector = $this->getConnectorById($id);

        if (!empty($data['name']) &&
            strtolower(trim($data['name'])) !== strtolower($connector->getName()) &&
            $this->connectorRepository->existsByNameInOrganization($data['name'], $connector->getOrganizationId(), $id)) {
            throw new BusinessConflictException("A connector with the same name already exists in this organization.");
        }

        $connector->updateDetails($data);
        return $this->connectorRepository->saveReturn($connector);
    }

    public function deleteConnector(int $id): void
    {
        $this->connectorRepository->delete($id, ['integrationEvents']);
    }
}
