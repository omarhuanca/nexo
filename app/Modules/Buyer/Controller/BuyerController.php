<?php

namespace App\Modules\Buyer\Controller;

use App\Http\Controllers\Controller;
use App\Http\Resources\BuyerResource;
use App\Http\Responses\ApiResponse;
use App\Modules\Buyer\Service\BuyerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Buyers',
    description: 'Read-only snapshots of the buyer information captured on each sale. A Buyer belongs to exactly one Sale and is created internally when the sale is submitted — it cannot be created, edited or deleted directly.'
)]
class BuyerController extends Controller
{
    private BuyerService $service;

    public function __construct(BuyerService $service)
    {
        $this->service = $service;
    }

    #[OA\Get(
        path: '/api/buyers',
        tags: ['Buyers'],
        summary: 'List buyer snapshots',
        description: 'Returns a paginated list of buyer snapshots, optionally filtered by sale_id or a partial name match.',
        operationId: 'indexBuyers',
        parameters: [
            new OA\Parameter(
                name: 'sale_id',
                in: 'query',
                required: false,
                description: 'Return only the buyer snapshot belonging to this sale.',
                schema: new OA\Schema(type: 'integer', example: 42)
            ),
            new OA\Parameter(
                name: 'name',
                in: 'query',
                required: false,
                description: 'Partial, case-insensitive match on the buyer name.',
                schema: new OA\Schema(type: 'string', example: 'Acme')
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
            new OA\Response(
                response: 200,
                description: 'Buyers retrieved successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Buyers retrieved successfully.'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'sale_id', type: 'integer', example: 42),
                                    new OA\Property(property: 'name', type: 'string', example: 'Empresa ABC'),
                                    new OA\Property(property: 'document_number', type: 'string', example: '12345678'),
                                ]
                            )
                        ),
                        new OA\Property(
                            property: 'pagination',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'last_page', type: 'integer', example: 1),
                                new OA\Property(property: 'per_page', type: 'integer', example: 15),
                                new OA\Property(property: 'total', type: 'integer', example: 5),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error.'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'sale_id' => 'sometimes|integer',
            'name' => 'sometimes|string|max:255',
            'perPage' => 'sometimes|integer|min:1|max:100',
        ]);

        $paginator = $this->service->paginate(
            $request->filled('sale_id') ? (int) $request->query('sale_id') : null,
            $request->query('name'),
            (int) $request->query('perPage', 15)
        );

        return ApiResponse::paginated('Buyers retrieved successfully.', $paginator, BuyerResource::class);
    }

    #[OA\Get(
        path: '/api/buyers/{id}',
        tags: ['Buyers'],
        summary: 'Get a buyer snapshot by ID',
        description: 'Returns a single buyer snapshot.',
        operationId: 'showBuyer',
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID of the buyer.',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Buyer retrieved successfully.'),
            new OA\Response(response: 404, description: 'Buyer not found.'),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $buyer = $this->service->getBuyerById($id);

        return ApiResponse::success('Buyer retrieved successfully.', 200, new BuyerResource($buyer));
    }
}
