<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Defaults for invoices fiscalized from a Xero webhook
    |--------------------------------------------------------------------------
    |
    | These only apply to invoices that were created directly in Xero (not
    | through POST /api/sales), where TaxCore-specific fields — invoice type,
    | transaction type, VAT label per item, payment type — have no equivalent
    | in Xero's Invoice object and cannot be derived from it.
    |
    */

    'default_invoice_type' => (int) env('TAXCORE_DEFAULT_INVOICE_TYPE', 0),
    'default_transaction_type' => (int) env('TAXCORE_DEFAULT_TRANSACTION_TYPE', 0),
    'default_vat_label' => env('TAXCORE_DEFAULT_VAT_LABEL', 'A'),
    'default_payment_type' => (int) env('TAXCORE_DEFAULT_PAYMENT_TYPE', 0),
];
