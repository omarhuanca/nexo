<?php

namespace App\Modules\Organization\Controller;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Http\Responses\ApiResponse;
use App\Modules\Organization\Service\OrganizationService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Organizations',
    description: 'Endpoints for managing organizations'
)]
class OrganizationController extends Controller
{
    private OrganizationService $organizationService;

    public function __construct(OrganizationService $organizationService)
    {
        $this->organizationService = $organizationService;
    }
    #[OA\Get(
        path: '/api/organizations',
        operationId: 'indexOrganizations',
        summary: 'List organizations',
        description: 'Returns a paginated list of organizations. Optionally filter by name using the search parameter.',
        tags: ['Organizations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'search',
                in: 'query',
                required: false,
                description: 'Filter organizations by name (case-insensitive partial match).',
                schema: new OA\Schema(type: 'string', example: 'Acme')
            ),
            new OA\Parameter(
                name: 'perPage',
                in: 'query',
                required: false,
                description: 'Number of results per page. Defaults to 10.',
                schema: new OA\Schema(type: 'integer', default: 10, example: 10)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Organizations retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Organizations retrieved successfully.'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'integer', example: 1),
                                    new OA\Property(property: 'name', type: 'string', example: 'Acme Corporation'),
                                    new OA\Property(property: 'tax_id', type: 'string', example: '12345678901'),
                                    new OA\Property(property: 'active', type: 'boolean', example: true),
                                ]
                            )
                        ),
                        new OA\Property(
                            property: 'pagination',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'current_page', type: 'integer', example: 1),
                                new OA\Property(property: 'last_page', type: 'integer', example: 3),
                                new OA\Property(property: 'per_page', type: 'integer', example: 10),
                                new OA\Property(property: 'total', type: 'integer', example: 25),
                                new OA\Property(property: 'from', type: 'integer', example: 1),
                                new OA\Property(property: 'to', type: 'integer', example: 10),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'A database error occurred.'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items()),
                    ]
                )
            ),
        ]
    )]
    public function index(\Illuminate\Http\Request $request): JsonResponse
    {
        $request->validate([
            'perPage' => 'sometimes|integer|min:1|max:100',
        ]);

        $search  = $request->query('search');
        $perPage = (int) $request->query('perPage', 10);
        $organizations = $this->organizationService->listOrganizations($search, $perPage);
        return ApiResponse::paginated(
            'Organizations retrieved successfully.',
            $organizations,
            OrganizationResource::class
        );
    }

    #[OA\Post(
        path: '/api/organizations',
        operationId: 'storeOrganization',
        summary: 'Create a new organization',
        description: 'Creates an organization after validating the request at both HTTP and domain levels. The name must be unique (case-insensitive).',
        tags: ['Organizations'],
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'tax_id', 'active'],
                properties: [
                    new OA\Property(
                        property: 'name',
                        description: 'Organization display name. Must be unique.',
                        type: 'string',
                        minLength: 3,
                        maxLength: 255,
                        example: 'Acme Corporation'
                    ),
                    new OA\Property(
                        property: 'tax_id',
                        description: 'Tax identification number. Digits and hyphens only.',
                        type: 'string',
                        minLength: 11,
                        maxLength: 20,
                        example: '12345678901'
                    ),
                    new OA\Property(
                        property: 'active',
                        description: 'Whether the organization is active.',
                        type: 'boolean',
                        example: true
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Organization created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Organization created successfully.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'Acme Corporation'),
                                new OA\Property(property: 'tax_id', type: 'string', example: '12345678901'),
                                new OA\Property(property: 'active', type: 'boolean', example: true),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error — request or domain',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Validation Error'),
                        new OA\Property(
                            property: 'errors',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'name',
                                    type: 'array',
                                    items: new OA\Items(type: 'string', example: 'The organization name must be at least 3 characters long.')
                                ),
                                new OA\Property(
                                    property: 'tax_id',
                                    type: 'array',
                                    items: new OA\Items(type: 'string', example: "The organization's tax ID must be at least 11 characters long.")
                                ),
                                new OA\Property(
                                    property: 'active',
                                    type: 'array',
                                    items: new OA\Items(type: 'string', example: 'The active field is required.')
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 409,
                description: 'Conflict — organization name already exists',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'An organization with the same name already exists.'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items()),
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'A database error occurred.'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items()),
                    ]
                )
            ),
        ]
    )]
    public function store(OrganizationRequest $request): JsonResponse
    {
        $validatedOrganization = $request->validated();

        $organization = $this->organizationService->createOrganization(
            $validatedOrganization['name'],
            $validatedOrganization['tax_id'],
            $validatedOrganization['active']
        );

        return ApiResponse::created(
            'Organization created successfully.',
            new OrganizationResource($organization)
        );
    }

    #[OA\Get(
        path: '/api/organizations/{id}',
        operationId: 'showOrganization',
        summary: 'Get an organization by ID',
        description: 'Returns a single organization by its ID.',
        tags: ['Organizations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID of the organization.',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Organization retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Organization retrieved successfully.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'Acme Corporation'),
                                new OA\Property(property: 'tax_id', type: 'string', example: '12345678901'),
                                new OA\Property(property: 'active', type: 'boolean', example: true),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Organization not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Organization not found with ID: 99'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items()),
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'A database error occurred.'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items()),
                    ]
                )
            ),
        ]
    )]
    public function show(int $id): JsonResponse
    {
        $organization = $this->organizationService->getOrganizationById($id);
        return ApiResponse::success(
            'Organization retrieved successfully.',
            200,
            new OrganizationResource($organization)
        );
    }

    #[OA\Put(
        path: '/api/organizations/{id}',
        operationId: 'updateOrganization',
        summary: 'Update an organization',
        description: 'Updates an existing organization. The name must remain unique (case-insensitive) across all organizations.',
        tags: ['Organizations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID of the organization to update.',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'tax_id', 'active'],
                properties: [
                    new OA\Property(
                        property: 'name',
                        description: 'Organization display name. Must be unique.',
                        type: 'string',
                        minLength: 3,
                        maxLength: 255,
                        example: 'Acme Corporation Updated'
                    ),
                    new OA\Property(
                        property: 'tax_id',
                        description: 'Tax identification number. Digits and hyphens only.',
                        type: 'string',
                        minLength: 11,
                        maxLength: 20,
                        example: '12345678901'
                    ),
                    new OA\Property(
                        property: 'active',
                        description: 'Whether the organization is active.',
                        type: 'boolean',
                        example: true
                    ),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Organization updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Organization updated successfully.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'Acme Corporation Updated'),
                                new OA\Property(property: 'tax_id', type: 'string', example: '12345678901'),
                                new OA\Property(property: 'active', type: 'boolean', example: true),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Organization not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Organization not found with ID: 99'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items()),
                    ]
                )
            ),
            new OA\Response(
                response: 409,
                description: 'Conflict — organization name already exists',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'An organization with the same name already exists.'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items()),
                    ]
                )
            ),
            new OA\Response(
                response: 422,
                description: 'Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Validation Error'),
                        new OA\Property(
                            property: 'errors',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'name',
                                    type: 'array',
                                    items: new OA\Items(type: 'string', example: 'The organization name must be at least 3 characters long.')
                                ),
                                new OA\Property(
                                    property: 'tax_id',
                                    type: 'array',
                                    items: new OA\Items(type: 'string', example: "The organization's tax ID must be at least 11 characters long.")
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'A database error occurred.'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items()),
                    ]
                )
            ),
        ]
    )]
    public function update(int $id, OrganizationRequest $request): JsonResponse
    {
        $validatedOrganization = $request->validated();

        $organization = $this->organizationService->updateOrganization($id, $validatedOrganization);

        return ApiResponse::success(
            'Organization updated successfully.',
            200,
            new OrganizationResource($organization)
        );
    }

    #[OA\Delete(
        path: '/api/organizations/{id}',
        operationId: 'destroyOrganization',
        summary: 'Delete an organization',
        description: 'Deletes an organization by ID. The operation is rejected if the organization has associated records (e.g. connectors) to avoid orphaned references.',
        tags: ['Organizations'],
        security: [['bearerAuth' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'ID of the organization to delete.',
                schema: new OA\Schema(type: 'integer', example: 1)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Organization deleted successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Organization deleted successfully.'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items()),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Organization not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Organization not found with ID: 1'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items()),
                    ]
                )
            ),
            new OA\Response(
                response: 409,
                description: 'Cannot delete — organization has associated records',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Cannot delete Organization: it has associated records in [connectors].'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items()),
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: 'Internal server error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'A database error occurred.'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items()),
                    ]
                )
            ),
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $this->organizationService->deleteOrganization($id);
        return ApiResponse::success('Organization deleted successfully.', 200);
    }
}