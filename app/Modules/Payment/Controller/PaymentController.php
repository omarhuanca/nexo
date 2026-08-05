<?php

namespace App\Modules\Payment\Controller;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentRequest;
use App\Http\Requests\PaymentUpdateRequest;
use App\Http\Resources\PaymentResource;
use App\Http\Responses\ApiResponse;
use App\Modules\Payment\Service\PaymentService;
use App\Modules\Sale\Domain\Sale;
use App\Shared\Exceptions\DomainValidationException;
use App\Shared\Exceptions\NotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Payments',
    description: "Per-sale payment snapshots. Each payment belongs to a Sale. Mutable catalog of available payment methods is not exposed here; payment_type is the TaxCore/V-SDC code (0-6)."
)]
class PaymentController extends Controller
{
    private PaymentService $service;

    public function __construct(PaymentService $service)
    {
        $this->service = $service;
    }

    #[OA\Get(
        path: '/api/sales/{saleId}/payments',
        tags: ['Payments'],
        summary: 'List payments of a sale',
        description: 'Returns the paginated payments applied to the given sale, ordered by sequence. Admin endpoint, no authentication required.',
        operationId: 'indexPayments',
        parameters: [
            new OA\Parameter(
                name: 'saleId',
                in: 'path',
                required: true,
                description: 'ID of the sale.',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
            new OA\Parameter(
                name: 'perPage',
                in: 'query',
                required: false,
                description: 'Number of results per page. Defaults to 15.',
                schema: new OA\Schema(type: 'integer', default: 15, example: 15)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Payments retrieved successfully.'),
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

        return ApiResponse::paginated('Payments retrieved successfully.', $paginator, PaymentResource::class);
    }

    #[OA\Post(
        path: '/api/sales/{saleId}/payments',
        tags: ['Payments'],
        summary: 'Create a payment for a sale',
        description: 'Persists a new payment snapshot linked to the given sale. Admin endpoint, no authentication required.',
        operationId: 'storePayment',
        parameters: [
            new OA\Parameter(
                name: 'saleId',
                in: 'path',
                required: true,
                description: 'ID of the sale.',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['amount', 'payment_type'],
                properties: [
                    new OA\Property(property: 'amount', type: 'number', format: 'float', example: 100.00),
                    new OA\Property(property: 'payment_type', type: 'integer', example: 1, description: '0=Other, 1=Cash, 2=Card, 3=Check, 4=WireTransfer, 5=Voucher, 6=MobileMoney'),
                    new OA\Property(property: 'sequence', type: 'integer', example: 0, description: 'Order in the invoice (defaults to 0).'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Payment created successfully.'),
            new OA\Response(response: 404, description: 'Sale not found.'),
            new OA\Response(response: 422, description: 'Validation error.'),
        ]
    )]
    public function store(PaymentRequest $request, int $saleId): JsonResponse
    {
        $sale = Sale::find($saleId);
        if (! $sale) {
            return ApiResponse::error('Sale not found.', 404);
        }

        try {
            $payment = $this->service->createPayment(
                $sale,
                (float) $request->input('amount'),
                (int) $request->input('payment_type'),
                (int) $request->input('sequence', 0)
            );
        } catch (DomainValidationException $e) {
            $errors = $e->getErrors();
            if (! empty($errors)) {
                return ApiResponse::validationError($errors);
            }
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::created('Payment created successfully.', new PaymentResource($payment));
    }

    #[OA\Get(
        path: '/api/payments/{id}',
        tags: ['Payments'],
        summary: 'Get a payment by ID',
        description: 'Returns a single payment snapshot. Admin endpoint, no authentication required.',
        operationId: 'showPayment',
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID of the payment.',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Payment retrieved successfully.'),
            new OA\Response(response: 404, description: 'Payment not found.'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $payment = $this->service->getPaymentById($id);

        return ApiResponse::success('Payment retrieved successfully.', 200, new PaymentResource($payment));
    }

    #[OA\Put(
        path: '/api/payments/{id}',
        tags: ['Payments'],
        summary: 'Update a payment',
        description: 'Updates amount, payment_type and/or sequence. Admin endpoint, no authentication required.',
        operationId: 'updatePayment',
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID of the payment to update.',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'amount', type: 'number', format: 'float', example: 100.00),
                    new OA\Property(property: 'payment_type', type: 'integer', example: 2),
                    new OA\Property(property: 'sequence', type: 'integer', example: 1),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Payment updated successfully.'),
            new OA\Response(response: 404, description: 'Payment not found.'),
            new OA\Response(response: 422, description: 'Validation error.'),
        ]
    )]
    public function update(PaymentUpdateRequest $request, int $id): JsonResponse
    {
        try {
            $data = $request->only(['amount', 'payment_type', 'sequence']);
            $payment = $this->service->updatePayment($id, $data);
        } catch (DomainValidationException $e) {
            $errors = $e->getErrors();
            if (! empty($errors)) {
                return ApiResponse::validationError($errors);
            }
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success('Payment updated successfully.', 200, new PaymentResource($payment));
    }

    #[OA\Delete(
        path: '/api/payments/{id}',
        tags: ['Payments'],
        summary: 'Delete a payment',
        description: 'Permanently removes a payment snapshot. Admin endpoint, no authentication required.',
        operationId: 'destroyPayment',
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID of the payment to delete.',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Payment deleted successfully.'),
            new OA\Response(response: 404, description: 'Payment not found.'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->service->deletePayment($id);
        } catch (NotFoundException $e) {
            return ApiResponse::error('Payment not found.', 404);
        }

        return ApiResponse::success('Payment deleted successfully.', 200);
    }
}
