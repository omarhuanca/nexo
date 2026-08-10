<?php

namespace App\Modules\LineItem\Controller;

use App\Http\Controllers\Controller;
use App\Http\Requests\LineItemRequest;
use App\Http\Resources\LineItemResource;
use App\Http\Responses\ApiResponse;
use App\Modules\LineItem\Service\LineItemService;
use App\Modules\Sale\Domain\Sale;
use App\Shared\Exceptions\DomainValidationException;
use App\Shared\Exceptions\NotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'LineItems',
    description: "Per-sale line-item snapshots. Each item belongs to a Sale. Mutable catalog of products is not exposed here; code is the Xero ItemCode."
)]
class LineItemController extends Controller
{
    private LineItemService $service;

    public function __construct(LineItemService $service)
    {
        $this->service = $service;
    }

    #[OA\Get(
        path: '/api/sales/{saleId}/lineItems',
        tags: ['LineItems'],
        summary: 'List line items of a sale',
        description: 'Returns the paginated line items of the given sale. Admin endpoint, no authentication required.',
        operationId: 'indexLineItems',
        parameters: [
            new OA\Parameter(name: 'saleId', in: 'path', required: true, description: 'ID of the sale.', schema: new OA\Schema(type: 'integer', example: 1)),
            new OA\Parameter(name: 'perPage', in: 'query', required: false, description: 'Number of results per page. Defaults to 15.', schema: new OA\Schema(type: 'integer', default: 15, example: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Line items retrieved successfully.'),
            new OA\Response(response: 404, description: 'Sale not found.'),
        ]
    )]
    public function index(Request $request, int $saleId): JsonResponse
    {
        $sale = Sale::find($saleId);
        if (! $sale) {
            return ApiResponse::error('Sale not found.', 404);
        }

        $paginator = $this->service->paginateBySale(
            $saleId,
            (int) $request->query('perPage', 15)
        );

        return ApiResponse::paginated('Line items retrieved successfully.', $paginator, LineItemResource::class);
    }

    #[OA\Post(
        path: '/api/sales/{saleId}/lineItems',
        tags: ['LineItems'],
        summary: 'Create a line item for a sale',
        description: 'Persists a new line-item snapshot linked to the given sale. quantity * unit_price must approximately equal total_amount (tolerance 0.01). Admin endpoint, no authentication required.',
        operationId: 'storeLineItem',
        parameters: [
            new OA\Parameter(name: 'saleId', in: 'path', required: true, description: 'ID of the sale.', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['code', 'name', 'quantity', 'unitPrice', 'totalAmount', 'labels', 'accountCode'],
                properties: [
                    new OA\Property(property: 'code', type: 'string', maxLength: 30, example: 'PROD-001'),
                    new OA\Property(property: 'name', type: 'string', maxLength: 2048, example: 'Producto A'),
                    new OA\Property(property: 'quantity', type: 'number', format: 'float', example: 2),
                    new OA\Property(property: 'unitPrice', type: 'number', format: 'float', example: 50.00),
                    new OA\Property(property: 'totalAmount', type: 'number', format: 'float', example: 100.00),
                    new OA\Property(property: 'labels', type: 'array', minItems: 1, items: new OA\Items(type: 'string', example: 'A')),
                    new OA\Property(property: 'accountCode', type: 'string', maxLength: 10, example: '200'),
                    new OA\Property(property: 'gtin', type: 'string', maxLength: 14, nullable: true, example: '12345678901234'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Line item created successfully.'),
            new OA\Response(response: 404, description: 'Sale not found.'),
            new OA\Response(response: 422, description: 'Validation error.'),
        ]
    )]
    public function store(LineItemRequest $request, int $saleId): JsonResponse
    {
        $sale = Sale::find($saleId);
        if (! $sale) {
            return ApiResponse::error('Sale not found.', 404);
        }

        try {
            $data = $request->only([
                'code', 'name', 'quantity', 'unit_price', 'total_amount',
                'labels', 'account_code', 'gtin',
            ]);
            $item = $this->service->createLineItem(
                $sale,
                $data['code'],
                $data['name'],
                (float) $data['quantity'],
                (float) $data['unit_price'],
                (float) $data['total_amount'],
                (array) $data['labels'],
                $data['account_code'],
                $data['gtin'] ?? null,
            );
        } catch (DomainValidationException $e) {
            $errors = $e->getErrors();
            if (! empty($errors)) {
                return ApiResponse::validationError($errors);
            }
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::created('Line item created successfully.', new LineItemResource($item));
    }

    #[OA\Get(
        path: '/api/lineItems/{id}',
        tags: ['LineItems'],
        summary: 'Get a line item by ID',
        description: 'Returns a single line-item snapshot. Admin endpoint, no authentication required.',
        operationId: 'showLineItem',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID of the line item.', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Line item retrieved successfully.'),
            new OA\Response(response: 404, description: 'Line item not found.'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $item = $this->service->getLineItemById($id);

        return ApiResponse::success('Line item retrieved successfully.', 200, new LineItemResource($item));
    }

    #[OA\Put(
        path: '/api/lineItems/{id}',
        tags: ['LineItems'],
        summary: 'Update a line item',
        description: 'Updates any of the line-item fields. quantity * unit_price must approximately equal total_amount (tolerance 0.01). Admin endpoint, no authentication required.',
        operationId: 'updateLineItem',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID of the line item to update.', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'code', type: 'string', maxLength: 30),
                    new OA\Property(property: 'name', type: 'string', maxLength: 2048),
                    new OA\Property(property: 'quantity', type: 'number', format: 'float'),
                    new OA\Property(property: 'unitPrice', type: 'number', format: 'float'),
                    new OA\Property(property: 'totalAmount', type: 'number', format: 'float'),
                    new OA\Property(property: 'labels', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'accountCode', type: 'string', maxLength: 10),
                    new OA\Property(property: 'gtin', type: 'string', nullable: true),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Line item updated successfully.'),
            new OA\Response(response: 404, description: 'Line item not found.'),
            new OA\Response(response: 422, description: 'Validation error.'),
        ]
    )]
    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'code' => 'sometimes|string|max:30',
            'name' => 'sometimes|string|max:2048',
            'quantity' => 'sometimes|numeric|min:0.001|max:999999',
            'unit_price' => 'sometimes|numeric|min:0|max:999999999.99',
            'total_amount' => 'sometimes|numeric|min:0|max:999999999.99',
            'labels' => 'sometimes|array|min:1',
            'labels.*' => 'sometimes|string|in:A,B,C,D,E,F,G,H',
            'account_code' => 'sometimes|string|max:10',
            'gtin' => 'sometimes|nullable|string|min:8|max:14|regex:/^\d+$/',
        ]);

        try {
            $data = $request->only([
                'code', 'name', 'quantity', 'unit_price', 'total_amount',
                'labels', 'account_code', 'gtin',
            ]);
            $item = $this->service->updateLineItem($id, $data);
        } catch (DomainValidationException $e) {
            $errors = $e->getErrors();
            if (! empty($errors)) {
                return ApiResponse::validationError($errors);
            }
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success('Line item updated successfully.', 200, new LineItemResource($item));
    }

    #[OA\Delete(
        path: '/api/lineItems/{id}',
        tags: ['LineItems'],
        summary: 'Delete a line item',
        description: 'Permanently removes a line-item snapshot. Admin endpoint, no authentication required.',
        operationId: 'destroyLineItem',
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, description: 'ID of the line item to delete.', schema: new OA\Schema(type: 'integer', example: 1)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Line item deleted successfully.'),
            new OA\Response(response: 404, description: 'Line item not found.'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->service->deleteLineItem($id);
        } catch (NotFoundException $e) {
            return ApiResponse::error('Line item not found.', 404);
        }

        return ApiResponse::success('Line item deleted successfully.', 200);
    }
}
