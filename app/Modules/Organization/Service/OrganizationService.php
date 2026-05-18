<?php

namespace App\Modules\Organization\Service;

use App\Modules\Organization\Repository\OrganizationRepository;
use App\Modules\Organization\Domain\Organization;
use App\Shared\Exceptions\BusinessConflictException;
use App\Shared\Helpers\Cleaner;

class OrganizationService
{
    private OrganizationRepository $organizationRepository;

    public function __construct(OrganizationRepository $organizationRepository)
    {
        $this->organizationRepository = $organizationRepository;
    }

    public function createOrganization(string $name, string $taxId, bool $active): Organization
    {
        if($this->organizationRepository->exists('name', Cleaner::cleanString($name))) 
            throw new BusinessConflictException("An organization with the same name already exists.");

        $organization = Organization::at($name, $taxId, $active);

        return $this->organizationRepository->saveReturn($organization);
    }
}