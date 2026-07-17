<?php

namespace App\Modules\Integration\Xero\Service;

use App\Modules\Connector\Repository\ConnectorRepository;
use App\Modules\Integration\Xero\Domain\XeroConnection;
use App\Modules\Integration\TaxCore\Service\TaxCoreSaleService;
use App\Modules\Sale\Domain\Sale;
use App\Modules\Sale\Repository\SaleRepository;
use Illuminate\Http\Request;
use Throwable;

class XeroWebhookService{

    public function __construct(
        private readonly XeroConnectionService $xeroConnectionService,
        private readonly XeroApiService $xeroApiService,
        private readonly XeroInvoiceMappingService $invoiceMappingService,
        private readonly ConnectorRepository $connectorRepository,
        private readonly SaleRepository $saleRepository,
        private readonly TaxCoreSaleService $taxCoreSaleService,
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

        if (!$connection) {
            return [];
        }

        $response = match ($resourceType) {
            'INVOICE' => $this->xeroApiService->get($connection, 'Invoices/' . $resourceId),
            'CONTACT' => $this->xeroApiService->get($connection, 'Contacts/' . $resourceId),
            default => null
        };

        return $response?->json() ?? [];
    }

    public function handleInvoiceEvent(?XeroConnection $connection, array $invoiceData): void
    {
        if (!$connection) {
            return;
        }

        $invoice = $invoiceData['Invoices'][0] ?? null;

        if (!$invoice || ($invoice['Type'] ?? null) !== 'ACCREC' || ($invoice['Status'] ?? null) !== 'AUTHORISED') {
            return;
        }

        $invoiceId = $invoice['InvoiceID'] ?? null;

        if (!$invoiceId) {
            return;
        }

        $existingSale = $this->saleRepository->findByXeroInvoiceId($invoiceId);

        if ($existingSale) {
            $existingSale->setXeroResult($invoiceData);
            $this->saleRepository->save($existingSale);
            return;
        }

        $organizationId = $connection->getOrganizationId();
        $connector = $this->connectorRepository->findFirstActiveByOrganization($organizationId);

        if (!$connector) {
            logger()->warning('Skipping Xero-originated invoice fiscalization: organization has no active connector.', [
                'organization_id' => $organizationId,
                'xero_invoice_id' => $invoiceId,
            ]);
            return;
        }

        try {
            $payload = $this->invoiceMappingService->mapToSalePayload($connection, $invoice);
        } catch (Throwable $e) {
            logger()->warning('Skipping Xero-originated invoice fiscalization: could not map invoice to a sale payload.', [
                'organization_id' => $organizationId,
                'xero_invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ]);
            return;
        }

        $sale = new Sale();
        $sale->setOrganizationId($organizationId);
        $sale->setConnectorId($connector->getId());
        $sale->setStatus('processing');
        $sale->setPayload($payload);
        $sale->setXeroInvoiceId($invoiceId);
        $sale->setXeroResult($invoiceData);
        $sale->setAttempts(1);

        $sale = $this->saleRepository->saveReturn($sale);

        $this->taxCoreSaleService->dispatchFiscalization($sale, $this->saleRepository);
    }
}