<?php

namespace App\Modules\Integration\Repository;

use App\Modules\Integration\Domain\Integration;
use App\Shared\Repository\AbstractRepository;

class IntegrationRepository extends AbstractRepository
{
    public function __construct(Integration $integration) 
    {
        parent::__construct($integration);
    }
}