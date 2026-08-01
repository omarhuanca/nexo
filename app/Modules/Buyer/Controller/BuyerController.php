<?php

namespace App\Modules\Buyer\Controller;

use App\Http\Controllers\Controller;
use App\Http\Requests\BuyerRequest;
use App\Http\Resources\BuyerResource;
use App\Http\Responses\ApiResponse;
use App\Modules\Buyer\Service\BuyerService;
use App\Modules\Organization\Domain\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Buyers',
    description: 'Catalog of buyers (customers) belonging to each organization. Buyer entries are mutable; immutable buyer snapshots inside sales continue to live in the Sale payload.'
)]
class BuyerController extends Controller
{
    public function __construct(private readonly BuyerService $service) {}

    #[OA\Get(
        path: '/api/buyers',
        tags: ['Buyers'],
        summary: 'List buyers of an organization',
        description: 'Returns a paginated list of buyers belonging to the given organization. Admin endpoint, no authentication required.',
        operationId: 'indexBuyers',
        parameters: [
            new OA\Parameter(
                name: 'organization_id',
                in: 'query',
                required: true,
                description: 'ID of the organization to filter buyers by.',
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
                                    new OA\Property(property: 'organization_id', type: 'integer', example: 1),
                                    new OA\Property(property: 'name', type: 'string', example: 'Empresa ABC'),
                                    new OA\Property(property: 'document_number', type: 'string', example: '12345678'),
                                    new OA\Property(property: 'active', type: 'boolean', example: true),
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
            new OA\Response(response: 422, description: 'Validation error — organization_id missing or invalid.'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'organization_id' => 'required|integer|exists:organizations,id',
            'perPage' => 'sometimes|integer|min:1|max:100',
        ]);

        $paginator = $this->service->paginateByOrganization(
            (int) $request->query('organization_id'),
            (int) $request->query('perPage', 15)
        );

        return ApiResponse::paginated('Buyers retrieved successfully.', $paginator, BuyerResource::class);
    }

    #[OA\Get(
        path: '/api/buyers/{id}',
        tags: ['Buyers'],
        summary: 'Get a buyer by ID',
        description: 'Returns a single buyer. Admin endpoint, no authentication required.',
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

        return ApiResponse::success(
            'Buyer retrieved successfully.',
            200,
            new BuyerResource($buyer)
        );
    }

    #[OA\Post(
        path: '/api/buyers',
        tags: ['Buyers'],
        summary: 'Create a new buyer',
        description: 'Creates a buyer in the catalog. The document_number must be unique within the organization. Admin endpoint, no authentication required.',
        operationId: 'storeBuyer',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['organization_id', 'name'],
                properties: [
                    new OA\Property(property: 'organization_id', type: 'integer', example: 1, description: 'ID of the organization.'),
                    new OA\Property(property: 'name', type: 'string', example: 'Empresa ABC', description: 'Buyer display name.'),
                    new OA\Property(property: 'document_number', type: 'string', example: '12345678', description: 'Optional document number (8-20 digits, country-agnostic).'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Buyer created successfully.'),
            new OA\Response(response: 409, description: 'Conflict — buyer with same document number already exists in this organization.'),
            new OA\Response(response: 422, description: 'Validation error.'),
        ]
    )]
    public function store(BuyerRequest $request): JsonResponse
    {
        $organization = Organization::findOrFail($request->integer('organization_id'));

        try {
            $buyer = $this->service->createBuyer(
                $organization,
                $request->input('name'),
                $request->input('document_number')
            );
        } catch (\App\Shared\Exceptions\BusinessConflictException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        } catch (\App\Shared\Exceptions\DomainValidationException $e) {
            $errors = $e->getErrors();
            if (! empty($errors)) {
                return ApiResponse::validationError($errors);
            }
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::created('Buyer created successfully.', new BuyerResource($buyer));
    }

    #[OA\Put(
        path: '/api/buyers/{id}',
        tags: ['Buyers'],
        summary: 'Update a buyer',
        description: 'Updates name and/or document_number. The document_number must remain unique within the organization. Admin endpoint, no authentication required.',
        operationId: 'updateBuyer',
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID of the buyer to update.',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Empresa Renombrada', description: 'New name.'),
                    new OA\Property(property: 'document_number', type: 'string', example: '87654321', description: 'New document number, or null to clear.'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Buyer updated successfully.'),
            new OA\Response(response: 404, description: 'Buyer not found.'),
            new OA\Response(response: 409, description: 'Conflict — buyer with same document number already exists in this organization.'),
            new OA\Response(response: 422, description: 'Validation error.'),
        ]
    )]
    public function update(int $id, Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
            'document_number' => 'sometimes|nullable|string|min:8|max:20|regex:/^\d+$/',
        ]);

        try {
            $data = $request->only(['name', 'document_number']);
            $buyer = $this->service->updateBuyer($id, $data);
        } catch (\App\Shared\Exceptions\BusinessConflictException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        } catch (\App\Shared\Exceptions\DomainValidationException $e) {
            $errors = $e->getErrors();
            if (! empty($errors)) {
                return ApiResponse::validationError($errors);
            }
            return ApiResponse::error($e->getMessage(), 422);
        }

        return ApiResponse::success('Buyer updated successfully.', 200, new BuyerResource($buyer));
    }

    #[OA\Delete(
        path: '/api/buyers/{id}',
        tags: ['Buyers'],
        summary: 'Delete a buyer',
        description: 'Permanently removes a buyer from the catalog. Admin endpoint, no authentication required.',
        operationId: 'destroyBuyer',
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID of the buyer to delete.',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Buyer deleted successfully.'),
            new OA\Response(response: 404, description: 'Buyer not found.'),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->service->deleteBuyer($id);
        } catch (\App\Shared\Exceptions\BusinessConflictException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        }

        return ApiResponse::success('Buyer deleted successfully.', 200);
    }
}
