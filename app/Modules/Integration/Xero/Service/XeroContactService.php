<?php

namespace App\Modules\Integration\Xero\Service;

use App\Modules\Integration\Xero\Domain\XeroConnection;
use App\Modules\Integration\Xero\Service\XeroApiService;

class XeroContactService
{
    public function __construct(private XeroApiService $xeroApiService) {}

    public function getContacts(XeroConnection $connection)
    {
        return $this->xeroApiService
            ->get($connection, 'Contacts')
            ->json();
    }
}