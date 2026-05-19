<?php

namespace App\Modules\Organization\Service;

use App\Modules\Organization\Repository\OrganizationRepository;
use App\Modules\Organization\Domain\Organization;
use App\Shared\Exceptions\BusinessConflictException;
use App\Shared\Helpers\Cleaner;
use Illuminate\Pagination\LengthAwarePaginator;

class OrganizationService
{
    private OrganizationRepository $organizationRepository;

    public function __construct(OrganizationRepository $organizationRepository)
    {
        $this->organizationRepository = $organizationRepository;
    }

    public function listOrganizations(?string $search = null, int $perPage = 10): LengthAwarePaginator
    {
        return $this->organizationRepository->searchPaginate($search, $perPage);
    }

    public function createOrganization(string $name, string $taxId, bool $active): Organization
    {
        if($this->organizationRepository->exists('name', Cleaner::cleanString($name))) 
            throw new BusinessConflictException("An organization with the same name already exists.");

        $organization = Organization::at($name, $taxId, $active);

        return $this->organizationRepository->saveReturn($organization);
    }

    public function getOrganizationById(int $id): Organization
    {
        return $this->organizationRepository->findById($id);
    }

    public function updateOrganization(int $id, array $data): Organization
    {
        $organization = $this->getOrganizationById($id);

        if(!empty($data['name']) && 
           $organization->getName() !== Cleaner::cleanString($data['name']) && 
           $this->organizationRepository->exists('name', Cleaner::cleanString($data['name']))) {
            throw new BusinessConflictException("An organization with the same name already exists.");
        }

        $organization->updateDetails($data);

        return $this->organizationRepository->saveReturn($organization);
    }

    public function deleteOrganization(int $id): void
    {
        $this->organizationRepository->delete($id /*['connectors', 'users']*/);
    }
}