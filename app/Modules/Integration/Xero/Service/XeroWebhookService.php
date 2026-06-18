<?php

namespace App\Modules\Integration\Xero\Service;

use Illuminate\Http\Request;

class XeroWebhookService{

    public function __construct(
        private readonly XeroConnectionService $xeroConnectionService,
        private readonly XeroApiService $xeroApiService
    ){}
    public function isValidSignature(Request $request): bool
    {
        $signature = $request->header('x-xero-signature');
        
        if (!$signature) return false;

        $body = $request->getContent();
        $expected = base64_encode(hash_hmac('sha256', $body, config('xero.webhook_key'), true));

        return hash_equals($expected, $signature);
    }

    public function getData(string $resourceType, string $resourceId, string $tenantId)
    {
        $connection = $this->xeroConnectionService->findByTenantId($tenantId);
        
        $response = match ($resourceType) {
            'INVOICE' => $this->xeroApiService->get($connection, 'Invoices/' . $resourceId),
            'CONTACT' => $this->xeroApiService->get($connection, 'Contacts/' . $resourceId),
            default => null
        };

        return $response?->json() ?? [];
    }
}