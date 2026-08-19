<?php

namespace App\Modules\Sale\Controller;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListSalesRequest;
use App\Http\Requests\SaleRequest;
use App\Http\Resources\SaleResource;
use App\Http\Responses\ApiResponse;
use App\Modules\Sale\Domain\ListSalesCriteria;
use App\Modules\Sale\Service\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Sales',
    description: "Endpoints for submitting sales. Each sale is registered asynchronously in both Xero (Invoice ACCREC) and TaxCore (fiscal signing). Requires the connector's Bearer token."
)]
class SaleController extends Controller
{
    public function __construct(private readonly SaleService $saleService) {}

    #[OA\Post(
        path: '/api/sales',
        tags: ['Sales'],
        summary: 'Submit a sale for processing',
        description: 'Accepts a sale payload and dispatches it for asynchronous processing. Step 1: creates a Xero Invoice (ACCREC). Step 2 (only if Xero succeeds): sends the invoice to TaxCore for fiscal signing. Returns immediately with a sale ID and pending status. Poll GET /api/sales/{id} for the result.',
        operationId: 'storeSale',
        security: [['connectorToken' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['invoiceType', 'transactionType', 'buyer', 'items', 'payment'],
                properties: [
                    new OA\Property(property: 'invoiceType', type: 'integer', enum: [0, 1, 2, 3, 4], example: 1, description: '0=Normal, 1=Proforma, 2=Copy, 3=Training, 4=Advance'),
                    new OA\Property(property: 'transactionType', type: 'integer', enum: [0, 1], example: 0, description: '0=Sale, 1=Refund'),
                    new OA\Property(property: 'cashier', type: 'string', maxLength: 50, example: 'Juan Pérez', nullable: true),
                    new OA\Property(property: 'dueDate', type: 'string', format: 'date', example: '2026-05-29', nullable: true, description: 'Invoice due date sent to Xero (YYYY-MM-DD). Defaults to today if omitted.'),
                    new OA\Property(property: 'referentDocumentNumber', type: 'string', maxLength: 50, nullable: true, example: null, description: 'Required for Refund (transactionType=1) or Copy/Advance (invoiceType=2 or 4).'),

                    new OA\Property(
                        property: 'buyer',
                        type: 'object',
                        required: ['name'],
                        properties: [
                            new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Empresa ABC d.o.o.', description: 'Buyer name used as Contact in Xero Invoice.'),
                            new OA\Property(property: 'id', type: 'string', maxLength: 20, example: '101234567', nullable: true, description: 'Buyer tax ID sent to TaxCore as buyerId.'),
                        ]
                    ),

                    new OA\Property(
                        property: 'items',
                        type: 'array',
                        minItems: 1,
                        items: new OA\Items(
                            required: ['code', 'name', 'quantity', 'unitPrice', 'totalAmount', 'labels', 'accountCode'],
                            properties: [
                                new OA\Property(property: 'code', type: 'string', maxLength: 30, example: 'PROD-001', description: 'Xero ItemCode — must match an existing Xero item.'),
                                new OA\Property(property: 'name', type: 'string', maxLength: 2048, example: 'Producto A'),
                                new OA\Property(property: 'quantity', type: 'number', format: 'float', example: 2),
                                new OA\Property(property: 'unitPrice', type: 'number', format: 'float', example: 50.00),
                                new OA\Property(property: 'totalAmount', type: 'number', format: 'float', example: 100.00, description: 'Sent to TaxCore.'),
                                new OA\Property(
                                    property: 'labels',
                                    type: 'array',
                                    minItems: 1,
                                    description: 'TaxCore fiscal tax labels.',
                                    items: new OA\Items(type: 'string', example: 'A')
                                ),
                                new OA\Property(property: 'accountCode', type: 'string', maxLength: 10, example: '200', description: 'Xero account code for the line item.'),
                                new OA\Property(property: 'gtin', type: 'string', nullable: true, example: null, description: 'Optional GTIN for TaxCore.'),
                            ]
                        )
                    ),

                    new OA\Property(
                        property: 'payment',
                        type: 'array',
                        minItems: 1,
                        items: new OA\Items(
                            required: ['amount', 'paymentType'],
                            properties: [
                                new OA\Property(property: 'amount', type: 'number', format: 'float', example: 100.00),
                                new OA\Property(property: 'paymentType', type: 'integer', enum: [0, 1, 2, 3, 4, 5, 6], example: 1, description: '0=Other, 1=Cash, 2=Card, 3=Check, 4=WireTransfer, 5=Voucher, 6=MobileMoney'),
                            ]
                        )
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 202,
                description: 'Sale accepted for processing.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Sale submitted for processing.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'status', type: 'string', example: 'pending'),
                            ]
                        ),
                    ]
                )
            ),

            new OA\Response(
                response: 401,
                description: 'Unauthorized — missing or invalid connector token.'
            ),

            new OA\Response(
                response: 422,
                description: 'Validation error — missing or invalid fields.'
            ),
        ]
    )]
    public function store(SaleRequest $request): JsonResponse
    {
        $connector = $request->attributes->get('connector');
        $sale = $this->saleService->createSale($connector, $request->validated());

        return ApiResponse::success('Sale submitted for processing.', 202, [
            'id' => $sale->getId(),
            'status' => $sale->getStatus(),
        ]);
    }

    #[OA\Get(
        path: '/api/sales/{id}',
        tags: ['Sales'],
        summary: 'Get sale processing result',
        description: 'Returns the current status and result of a previously submitted sale. Poll this endpoint until status is completed or failed. On completed, fiscal_number and xero_invoice_id are populated.',
        operationId: 'showSale',
        security: [['connectorToken' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Sale record retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Sale retrieved.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(
                                    property: 'status',
                                    type: 'string',
                                    enum: ['pending', 'processing', 'completed', 'failed'],
                                    example: 'completed'
                                ),
                                new OA\Property(
                                    property: 'xero_invoice_id',
                                    type: 'string',
                                    nullable: true,
                                    example: 'a94a8fe5-ccb1-4f4c-b49e-e1d8ae3e4f5b'
                                ),
                                new OA\Property(
                                    property: 'fiscal_number',
                                    type: 'string',
                                    nullable: true,
                                    example: '7YTQL8TY-8TQL-4900-3323-5L8TQLM7FCME'
                                ),
                                new OA\Property(
                                    property: 'fiscal_result',
                                    type: 'object',
                                    nullable: true,
                                    description: 'Full TaxCore fiscal response.'
                                ),
                                new OA\Property(
                                    property: 'xero_result',
                                    type: 'object',
                                    nullable: true,
                                    description: 'Full Xero Invoice response.'
                                ),
                                new OA\Property(
                                    property: 'error_message',
                                    type: 'string',
                                    nullable: true,
                                    example: null
                                ),
                                new OA\Property(
                                    property: 'attempts',
                                    type: 'integer',
                                    example: 1
                                ),
                                new OA\Property(
                                    property: 'processed_at',
                                    type: 'string',
                                    format: 'date-time',
                                    nullable: true
                                ),
                            ]
                        ),
                    ]
                )
            ),

            new OA\Response(
                response: 401,
                description: 'Unauthorized — missing or invalid connector token.'
            ),

            new OA\Response(
                response: 404,
                description: 'Sale not found.'
            ),
        ]
    )]
    public function show(Request $request, int $id): JsonResponse
    {
        $connector = $request->attributes->get('connector');
        $sale = $this->saleService->getSaleById($id, $connector->getOrganizationId());

        return ApiResponse::success('Sale retrieved.', 200, $sale);
    }

    #[OA\Get(
        path: '/api/integrations/taxcore/invoices',
        tags: ['TaxCore Integration'],
        summary: 'List fiscalized invoices (read from local DB)',
        description: 'Returns invoices persisted in the `sales` table after being signed by TaxCore. By default returns only `status=completed`. Supports filtering by invoice/transaction type, fiscal number and date range. organization_id is required. No authentication required.',
        operationId: 'listTaxcoreInvoices',
        parameters: [
            new OA\Parameter(name: 'organization_id', in: 'query', required: true, description: 'ID of the organization whose invoices to list.', schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'status', in: 'query', required: false, description: 'Filter by status. Defaults to completed.', schema: new OA\Schema(type: 'string', enum: ['pending', 'processing', 'completed', 'failed'], example: 'completed')),
            new OA\Parameter(name: 'invoiceType', in: 'query', required: false, description: '0=Normal, 1=ProForma, 2=Copy, 3=Training, 4=Advance.', schema: new OA\Schema(type: 'integer', enum: [0, 1, 2, 3, 4])),
            new OA\Parameter(name: 'transactionType', in: 'query', required: false, description: '0=Sale, 1=Refund.', schema: new OA\Schema(type: 'integer', enum: [0, 1])),
            new OA\Parameter(name: 'fiscalNumber', in: 'query', required: false, description: 'Exact fiscal invoice number (TaxCore invoiceNumber).', schema: new OA\Schema(type: 'string', example: 'CPLP77KX-Dt1Ov1o0-5')),
            new OA\Parameter(name: 'dateFrom', in: 'query', required: false, description: 'Start date (YYYY-MM-DD). Filters by created_at.', schema: new OA\Schema(type: 'string', format: 'date', example: '2026-05-01')),
            new OA\Parameter(name: 'dateTo', in: 'query', required: false, description: 'End date (YYYY-MM-DD). Must be on or after dateFrom.', schema: new OA\Schema(type: 'string', format: 'date', example: '2026-05-31')),
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, example: 1)),
            new OA\Parameter(name: 'pageSize', in: 'query', required: false, description: 'Items per page (max 100).', schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, example: 50)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Invoices retrieved successfully.'),
            new OA\Response(response: 422, description: 'Validation error — organization_id is required.'),
        ]
    )]
    public function index(ListSalesRequest $request): JsonResponse
    {
        $criteria = ListSalesCriteria::fromArray($request->validated());
        $paginator = $this->saleService->listInvoices(
            $request->integer('organization_id'),
            $criteria
        );

        return ApiResponse::paginated('Invoices retrieved.', $paginator, SaleResource::class);
    }

    #[OA\Get(
        path: '/api/integrations/taxcore/invoices/{id}',
        tags: ['TaxCore Integration'],
        summary: 'Get a single fiscalized invoice (read from local DB)',
        description: 'Returns the full Sale record by its internal ID, including the original request payload, the TaxCore signed response and the Xero response. Reads from the `sales` table. organization_id is required and the sale is verified to belong to that organization. No authentication required.',
        operationId: 'getTaxcoreInvoice',
        parameters: [
            new OA\Parameter(name: 'organization_id', in: 'query', required: true, description: 'ID of the organization that owns the invoice.', schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'Internal Sale ID.', schema: new OA\Schema(type: 'integer', example: 12)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Invoice retrieved successfully.'),
            new OA\Response(response: 404, description: 'Invoice not found for this organization.'),
            new OA\Response(response: 422, description: 'Validation error — organization_id is required.'),
        ]
    )]
    public function getInvoice(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'organization_id' => 'required|integer|exists:organizations,id',
        ]);

        $sale = $this->saleService->getInvoice(
            $id,
            $request->integer('organization_id')
        );

        return ApiResponse::success('Invoice retrieved.', 200, SaleResource::make($sale));
    }
}
