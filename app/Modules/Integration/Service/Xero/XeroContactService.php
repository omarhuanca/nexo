<?php

namespace App\Modules\Integration\Service\Xero;

use App\Modules\Integration\Domain\Xero\XeroConnection;
use App\Modules\Integration\Service\Xero\XeroApiService;

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