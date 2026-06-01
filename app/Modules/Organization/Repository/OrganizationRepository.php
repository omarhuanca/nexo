<?php

namespace App\Modules\Organization\Repository;
use App\Shared\Repository\AbstractRepository;
use App\Shared\Repository\RepositoryInterface;
use App\Modules\Organization\Domain\Organization;

class OrganizationRepository extends AbstractRepository implements RepositoryInterface
{
    public function __construct(Organization $organization)
    {
        parent::__construct($organization);
    }
}