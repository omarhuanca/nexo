<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Invoice header
            'invoiceType'               => 'required|integer|in:0,1,2,3,4',
            'transactionType'           => 'required|integer|in:0,1',
            'cashier'                   => 'nullable|string|max:50',
            'dueDate'                   => 'nullable|date_format:Y-m-d',
            'referentDocumentNumber' => 'required_if:transactionType,1|required_if:invoiceType,2|required_if:invoiceType,4|nullable|string|max:50',
            'referentDocumentDT' => 'nullable|string',

            // Buyer (always required with name)
            'buyer' => 'required|array',
            'buyer.name' => 'required|string|max:255',
            'buyer.id' => 'nullable|string|max:20',

            // Items (fields for both Xero and TaxCore)
            'items' => 'required|array|min:1',
            'items.*.code' => 'required|string|max:30',
            'items.*.name' => 'required|string|max:2048',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unitPrice' => 'required|numeric',
            'items.*.totalAmount' => 'required|numeric',
            'items.*.labels' => 'required|array|min:1',
            'items.*.labels.*' => 'required|string',
            'items.*.accountCode' => 'required|string|max:10',
            'items.*.gtin' => 'nullable|string|min:8|max:14',

            // Payment
            'payment' => 'required|array|min:1',
            'payment.*.amount' => 'required|numeric',
            'payment.*.paymentType' => 'required|integer|in:0,1,2,3,4,5,6',
        ];
    }

    public function messages(): array
    {
        return [
            'referentDocumentNumber.required_if' => 'The referentDocumentNumber is required for Refund and Copy/Advance invoices.',
        ];
    }
}
