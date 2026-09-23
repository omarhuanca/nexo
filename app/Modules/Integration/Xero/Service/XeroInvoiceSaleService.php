<?php

namespace App\Modules\Integration\Xero\Service;

use App\Modules\Integration\Xero\Domain\XeroConnection;
use App\Modules\Sale\Domain\Sale;

class XeroInvoiceSaleService
{
    public function __construct(private readonly XeroApiService $apiService) {}

    public function createInvoice(XeroConnection $connection, Sale $sale): array
    {
        $payload = $sale->getPayload();
        $today = now()->format('Y-m-d');

        $xeroPayload = [
            'Type'    => 'ACCREC',
            'Status'  => 'AUTHORISED',
            'Date'    => $today,
            'DueDate' => $payload['dueDate'] ?? $today,
            'Contact' => [
                'Name' => $sale->buyer->name,
            ],
            'LineItems' => $sale->lineItems->map(
                fn($item) => [
                    'ItemCode' => $item->code,
                    'Description' => $item->name,
                    'Quantity' => $item->quantity,
                    'UnitAmount' => $item->unit_price,
                    'AccountCode' => $item->account_code,
                ]
            )->toArray(),
        ];

        $response = $this->apiService->post($connection, 'Invoices', $xeroPayload);

        return $response->json();
    }

    /**
     * Writes the TaxCore fiscal number into the invoice Reference and attaches the
     * verification URL (and QR image, when V-SDC returned one) so it is visible online.
     */
    public function applyFiscalReference(XeroConnection $connection, Sale $sale): void
    {
        $invoiceId = $sale->getXeroInvoiceId();
        $fiscalNumber = (string) $sale->getFiscalNumber();
        $fiscal = is_array($sale->getFiscalResult()) ? $sale->getFiscalResult() : [];

        $this->apiService->post($connection, "Invoices/{$invoiceId}", [
            'InvoiceID' => $invoiceId,
            'Reference' => $fiscalNumber,
        ]);

        $baseName = 'fiscal-' . preg_replace('/[^A-Za-z0-9_-]/', '_', $fiscalNumber);
        $verificationUrl = $fiscal['verificationUrl'] ?? null;

        if ($verificationUrl) {
            $content = "Fiscal invoice number: {$fiscalNumber}\r\nVerification URL: {$verificationUrl}\r\n";
            $this->apiService->putRaw(
                $connection,
                "Invoices/{$invoiceId}/Attachments/{$baseName}.txt?IncludeOnline=true",
                $content,
                'text/plain',
            );
        }

        $qr = !empty($fiscal['verificationQRCode']) ? base64_decode($fiscal['verificationQRCode'], true) : false;

        if ($qr !== false) {
            $this->apiService->putRaw(
                $connection,
                "Invoices/{$invoiceId}/Attachments/{$baseName}-qr.gif?IncludeOnline=true",
                $qr,
                'image/gif',
            );
        }
    }
}
