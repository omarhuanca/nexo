<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TaxCoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'organization_id'         => 'required|integer|exists:organizations,id',

            // Invoice header
            'invoiceType'             => 'required|integer|in:0,1,2,3,4',
            'transactionType'         => 'required|integer|in:0,1',
            'dateAndTimeOfIssue'      => 'nullable|string',
            'cashier'                 => 'nullable|string|max:50',
            'buyerId'                 => 'nullable|string|max:20',
            'buyerCostCenterId'       => 'nullable|string|max:50',
            'invoiceNumber'           => 'nullable|string|max:60',

            // Required for Refund (transactionType=1) and Copy/Advance (invoiceType 2 or 4)
            'referentDocumentNumber'  => 'required_if:transactionType,1|required_if:invoiceType,2|required_if:invoiceType,4|nullable|string|max:50',
            'referentDocumentDT'      => 'nullable|string',

            // Items
            'items'                   => 'required|array|min:1',
            'items.*.name'            => 'required|string|max:2048',
            'items.*.quantity'        => 'required|numeric|min:0.001',
            'items.*.unitPrice'       => 'required|numeric',
            'items.*.totalAmount'     => 'required|numeric',
            'items.*.labels'          => 'required|array|min:1',
            'items.*.labels.*'        => 'required|string',
            'items.*.gtin'            => 'nullable|string|min:8|max:14',

            // Payments
            'payment'                 => 'required|array|min:1',
            'payment.*.amount'        => 'required|numeric',
            'payment.*.paymentType'   => 'required|integer|in:0,1,2,3,4,5,6',
        ];
    }

    public function messages(): array
    {
        return [
            'referentDocumentNumber.required_if' => 'The referentDocumentNumber field is required for Refund and Copy/Advance invoices.',
        ];
    }
}
