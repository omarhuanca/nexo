<?php

namespace App\Modules\IntegrationEvent\Repository;

use App\Modules\IntegrationEvent\Domain\IntegrationEvent;
use App\Shared\Repository\AbstractRepository;

class IntegrationEventRepository extends AbstractRepository
{
    public function __construct(IntegrationEvent $integrationEvent)
    {
        parent::__construct($integrationEvent);
    }
}