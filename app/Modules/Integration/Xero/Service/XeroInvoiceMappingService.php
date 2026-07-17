<?php

namespace App\Modules\Integration\Xero\Service;

use App\Modules\Integration\Xero\Domain\XeroConnection;
use InvalidArgumentException;
class XeroInvoiceMappingService
{
    public function __construct(private readonly XeroApiService $apiService) {}

    public function mapToSalePayload(XeroConnection $connection, array $invoice): array
    {
        $lineItems = $invoice['LineItems'] ?? [];

        if (empty($lineItems)) {
            throw new InvalidArgumentException('Xero invoice has no LineItems.');
        }

        $contactName = $invoice['Contact']['Name'] ?? null;

        if (!$contactName) {
            throw new InvalidArgumentException('Xero invoice has no Contact name.');
        }

        return [
            'invoiceType' => config('taxcore.default_invoice_type'),
            'transactionType' => config('taxcore.default_transaction_type'),
            'cashier' => null,
            'buyer' => [
                'name' => $contactName,
                'id' => $this->resolveBuyerTaxNumber($connection, $invoice['Contact']['ContactID'] ?? null),
            ],
            'items' => array_map(fn(array $item) => [
                'name' => $item['Description'] ?? $item['Item']['Name'] ?? 'Item',
                'quantity' => (float) ($item['Quantity'] ?? 1),
                'unitPrice' => (float) ($item['UnitAmount'] ?? 0),
                'totalAmount' => (float) ($item['LineAmount'] ?? 0),
                'labels' => [config('taxcore.default_vat_label')],
                'gtin' => null,
            ], $lineItems),
            'payment' => [
                [
                    'amount' => (float) ($invoice['Total'] ?? 0),
                    'paymentType' => config('taxcore.default_payment_type'),
                ],
            ],
        ];
    }

    private function resolveBuyerTaxNumber(XeroConnection $connection, ?string $contactId): ?string
    {
        if (!$contactId) {
            return null;
        }

        $response = $this->apiService->get($connection, "Contacts/{$contactId}");

        return $response->json('Contacts.0.TaxNumber');
    }
}
