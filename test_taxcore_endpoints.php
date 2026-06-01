<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$svc = app(App\Modules\Integration\TaxCore\Service\TaxCoreConnectionService::class);
$api = app(App\Modules\Integration\TaxCore\Service\TaxCoreApiService::class);
$conn = $svc->findActiveByOrganization(1);

// --- Training Sale (invoiceType=3 — no fiscal effect, safe for testing) ---
$invoice = [
    'invoiceType'        => 3,  // Training
    'transactionType'    => 0,  // Sale
    'dateAndTimeOfIssue' => date('Y-m-d\TH:i:sP'),
    'cashier'            => 'TestCashier',
    'items' => [
        [
            'name'        => 'Test Product',
            'quantity'    => 1,
            'unitPrice'   => 100.00,
            'totalAmount' => 100.00,
            'labels'      => ['A'],
        ],
    ],
    'payment' => [
        ['amount' => 100.00, 'paymentType' => 1],  // Cash
    ],
];

try {
    $r = $api->post($conn, '/api/v3/invoices', $invoice);
    echo "Training Sale => HTTP " . $r->status() . PHP_EOL;
    $data = $r->json();
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

    // If successful, show the invoice number for refund test
    if (!empty($data['invoiceNumber'])) {
        echo PHP_EOL . "Invoice Number: " . $data['invoiceNumber'] . PHP_EOL;
        echo "Use this as referentDocumentNumber for a refund." . PHP_EOL;
    }
} catch (Throwable $e) {
    echo "ERR: " . $e->getMessage() . PHP_EOL;
}
