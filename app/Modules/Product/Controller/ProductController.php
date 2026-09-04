<?php

namespace App\Modules\Product\Controller;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Http\Responses\ApiResponse;
use App\Modules\Organization\Domain\Organization;
use App\Modules\Product\Service\ProductService;
use App\Shared\Exceptions\BusinessConflictException;
use App\Shared\Exceptions\DomainValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Products',
    description: 'Base product catalog belonging to each organization.'
)]
class ProductController extends Controller
{
    private ProductService $service;

    public function __construct(ProductService $service) {
        $this->service = $service;
    }
    #[OA\Get(
        path: '/api/products',
        tags: ['Products'],
        summary: 'List products of an organization',
        description: 'Returns a paginated list of products belonging to the given organization. Admin endpoint, no authentication required.',
        operationId: 'indexProducts',
        parameters: [
            new OA\Parameter(
                name: 'organization_id',
                in: 'query',
                required: true,
                description: 'ID of the organization to filter products by.',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
            new OA\Parameter(
                name: 'perPage',
                in: 'query',
                required: false,
                description: 'Number of results per page. Defaults to 15; maximum is 100.',
                schema: new OA\Schema(type: 'integer', default: 15, minimum: 1, maximum: 100, example: 15)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Products retrieved successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Products retrieved successfully.'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'organization_id', type: 'integer', example: 1),
                                    new OA\Property(property: 'code', type: 'string', example: 'PROD-001'),
                                    new OA\Property(property: 'name', type: 'string', example: 'Producto A'),
                                    new OA\Property(property: 'description', type: 'string', example: 'Descripcion del producto.'),
                                    new OA\Property(property: 'salePrice', type: 'number', format: 'float', example: 50),
                                    new OA\Property(property: 'costPrice', type: 'number', format: 'float', example: 35),
                                    new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-09-04T12:00:00Z'),
                                    new OA\Property(property: 'updated_at', type: 'string', format: 'date-time', example: '2026-09-04T12:00:00Z'),
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
                                new OA\Property(property: 'from', type: 'integer', example: 1),
                                new OA\Property(property: 'to', type: 'integer', example: 5),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error - organization_id is missing or invalid.'),
        ]
    )]

    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'organization_id' => 'required|integer|exists:organizations,id',
            'perPage' => 'sometimes|integer|min:1|max:100',
        ]);

        $products = $this->service->paginateByOrganization(
            (int) $request->query('organization_id'),
            (int) $request->query('perPage', 15),
        );

        return ApiResponse::paginated(
            'Products retrieved successfully.',
            $products,
            ProductResource::class,
        );
    }

    #[OA\Post(
        path: '/api/products',
        tags: ['Products'],
        summary: 'Create a product',
        description: 'Creates a product in the base catalog of an organization. The code must be unique within that organization. Admin endpoint, no authentication required.',
        operationId: 'storeProduct',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['organization_id', 'code', 'name', 'salePrice', 'costPrice'],
                properties: [
                    new OA\Property(property: 'organization_id', type: 'integer', example: 1, description: 'ID of the organization.'),
                    new OA\Property(property: 'code', type: 'string', maxLength: 30, example: 'PROD-001', description: 'Product code, unique within the organization.'),
                    new OA\Property(property: 'name', type: 'string', maxLength: 255, example: 'Producto A'),
                    new OA\Property(property: 'description', type: 'string', maxLength: 2048, default: '', example: 'Descripcion del producto.'),
                    new OA\Property(property: 'salePrice', type: 'number', format: 'float', minimum: 0, example: 50),
                    new OA\Property(property: 'costPrice', type: 'number', format: 'float', minimum: 0, example: 35),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Product created successfully.'),
            new OA\Response(response: 409, description: 'Conflict - a product with the same code already exists in this organization.'),
            new OA\Response(response: 422, description: 'Validation error.'),
        ]
    )]
    public function store(ProductRequest $request): JsonResponse
    {
        $organization = Organization::findOrFail(
            $request->integer('organization_id')
        );

        try {
            $product = $this->service->createProduct(
                $organization,
                $request->string('code')->toString(),
                $request->string('name')->toString(),
                $request->input('description', ''),
                (float) $request->input('salePrice'),
                (float) $request->input('costPrice'),
            );
        } catch (BusinessConflictException $exception) {
            return ApiResponse::error($exception->getMessage(), 409);
        } catch (DomainValidationException $exception) {
            $errors = $exception->getErrors();
            if (! empty($errors)) {
                return ApiResponse::validationError($errors);
            }

            return ApiResponse::error($exception->getMessage(), 422);
        }

        return ApiResponse::created(
            'Product created successfully.',
            new ProductResource($product),
        );
    }
}
