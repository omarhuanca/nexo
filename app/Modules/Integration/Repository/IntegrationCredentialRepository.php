<?php

namespace App\Modules\Integration\Repository;

use App\Modules\Integration\Domain\IntegrationCredential;
use App\Shared\Repository\AbstractRepository;

class IntegrationCredentialRepository
    extends AbstractRepository
{
    public function __construct(IntegrationCredential $credential) 
    {
        parent::__construct($credential);
    }
}