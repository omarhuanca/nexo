<?php

namespace App\Modules\Connector\Controller;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateConnectorRequest;
use App\Http\Requests\UpdateConnectorRequest;
use App\Http\Resources\ConnectorResource;
use App\Http\Responses\ApiResponse;
use App\Modules\Connector\Service\ConnectorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;


#[OA\Tag(
    name:"Connectors",
    description:"Endpoints for managing connectors. A connector represents an external system integration point linked to an organization."
)]
class ConnectorController extends Controller
{
    public function __construct(private readonly ConnectorService $connectorService) {}

    #[OA\Get(
    path: '/api/connectors',
    tags: ['Connectors'],
    summary: 'List connectors of an organization',
    description: 'Returns a paginated list of connectors belonging to the given organization.',
    operationId: 'indexConnectors',

    parameters: [
        new OA\Parameter(
            name: 'organization_id',
            in: 'query',
            required: true,
            description: 'ID of the organization to filter connectors by.',
            schema: new OA\Schema( type: 'integer', example: 1)
        ),
        new OA\Parameter(
            name: 'perPage',
            in: 'query',
            required: false,
            description: 'Number of results per page. Defaults to 10.',
            schema: new OA\Schema( type: 'integer', default: 10, example: 10)
        ),
    ],

    responses: [
        new OA\Response(
            response: 200,
            description: 'Connectors retrieved successfully',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'success', type: 'boolean', example: true),
                    new OA\Property( property: 'message', type: 'string', example: 'Connectors retrieved successfully.' ),
                    new OA\Property(
                        property: 'data',
                        type: 'array',
                        items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'id',type: 'integer',example: 1),
                                new OA\Property(property: 'organization_id',type: 'integer',example: 1),
                                new OA\Property(property: 'name',type: 'string',example: 'POS System'),
                                new OA\Property(property: 'active', type: 'boolean', example: true ),
                                new OA\Property(property: 'allowed_events', type: 'array', nullable: true, items: new OA\Items(type: 'string',example: 'invoice.created') ),
                                new OA\Property(property: 'last_used_at',type: 'string',format: 'date-time',nullable: true,example: '2026-05-19T10:00:00.000000Z'),
                                new OA\Property(property: 'created_at',type: 'string',format: 'date-time',example: '2026-05-19T08:00:00.000000Z'),
                            ]
                        )
                    ),
                    new OA\Property(
                        property: 'pagination',
                        properties: [
                            new OA\Property(property: 'current_page', type: 'integer', example: 1 ),
                            new OA\Property(property: 'last_page',type: 'integer',example: 2),
                            new OA\Property(property: 'per_page',type: 'integer',example: 10),
                            new OA\Property(property: 'total',type: 'integer',example: 15),
                            new OA\Property(property: 'from',type: 'integer',example: 1),
                            new OA\Property(property: 'to',type: 'integer',example: 10),
                        ]
                    ),
                ]
            )
        ),

        new OA\Response(
            response: 422,
            description: 'Validation error',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'success',type: 'boolean',example: false),
                    new OA\Property(property: 'message',type: 'string',example: 'Validation Error'),
                ]
            )
        ),

        new OA\Response(
            response: 500,
            description: 'Internal server error',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'success',type: 'boolean',example: false),
                    new OA\Property(property: 'message',type: 'string',example: 'A database error occurred.'),
                ]
            )
        )
    ]
)]
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'organization_id' => 'required|integer|exists:organizations,id',
            'perPage' => 'sometimes|integer|min:1|max:100',
        ]);

        $connectors = $this->connectorService->listConnectorsByOrganization(
            (int) $request->query('organization_id'),
            (int) $request->query('perPage', 10)
        );

        return ApiResponse::paginated(
            'Connectors retrieved successfully.',
            $connectors,
            ConnectorResource::class
        );
    }

    #[OA\Post(
        path: '/api/connectors',
        tags: ['Connectors'],
        summary: 'Create a new connector',
        description: 'Creates a connector for the given organization. The token is only returned in this response and cannot be retrieved later.',
        operationId: 'storeConnector',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['organization_id', 'name'],
                properties: [
                    new OA\Property(property: 'organization_id', type: 'integer', description: 'ID of the organization this connector belongs to.', example: 1),
                    new OA\Property(property: 'name', type: 'string', minLength: 3, maxLength: 255, description: 'Unique name within the organization.', example: 'POS System'),
                    new OA\Property(property: 'allowed_events',type: 'array',nullable: true,description: 'List of event types this connector is allowed to send. Null means all events are allowed.',items: new OA\Items(type: 'string', example: 'invoice.created')),
                    new OA\Property(property: 'active', type: 'boolean', description: 'Whether the connector is active. Defaults to true.', example: true),
                ]
            )
        ),

        responses: [
            new OA\Response(
                response: 201,
                description: 'Connector created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Connector created successfully.'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'organization_id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'POS System'),
                                new OA\Property(property: 'active', type: 'boolean', example: true),
                                new OA\Property(property: 'allowed_events',type: 'array',nullable: true,items: new OA\Items(type: 'string', example: 'invoice.created')),
                                new OA\Property(property: 'last_used_at', type: 'string', nullable: true, example: null),
                                new OA\Property(property: 'token', type: 'string', description: 'Bearer token — only visible at creation.', example: 'a3f1c2e4d5b6a7f8c9e0d1b2a3f4c5e6'),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-05-19T08:00:00.000000Z'),
                            ]
                        ),
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
                            properties: [
                                new OA\Property( property: 'name', type: 'array', items: new OA\Items(type: 'string', example: 'The name field is required.') ),
                            ]
                        ),
                    ]
                )
            ),

            new OA\Response(
                response: 409,
                description: 'Conflict — connector name already exists in this organization',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'A connector with the same name already exists in this organization.'),
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
    public function store(CreateConnectorRequest $request): JsonResponse
    {
        $data = $request->safe()->only(['organization_id', 'name', 'allowed_events', 'active']);
        $connector = $this->connectorService->createConnector(
            (int) $data['organization_id'],
            $data['name'],
            $data['allowed_events'] ?? [],
            $data['active'] ?? true
        );

        return ApiResponse::created(
            'Connector created successfully.',
            new ConnectorResource($connector)
        );
    }

    #[OA\Get(
        path: '/api/connectors/{id}',
        tags: ['Connectors'],
        summary: 'Get a connector by ID',
        description: 'Returns a single connector. The token is never exposed in this response.',
        operationId: 'showConnector',

        parameters: [
            new OA\Parameter(name: 'id',in: 'path',required: true,description: 'ID of the connector.',schema: new OA\Schema(type: 'integer', example: 1)),
        ],

        responses: [
            new OA\Response(
                response: 200,
                description: 'Connector retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Connector retrieved successfully.'),
                        new OA\Property(
                            property: 'data',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'organization_id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'POS System'),
                                new OA\Property(property: 'active', type: 'boolean', example: true),
                                new OA\Property(property: 'allowed_events',type: 'array',nullable: true,items: new OA\Items(type: 'string', example: 'invoice.created')),
                                new OA\Property(property: 'last_used_at', type: 'string', format: 'date-time', nullable: true, example: null),
                                new OA\Property(property: 'created_at', type: 'string', format: 'date-time', example: '2026-05-19T08:00:00.000000Z'),
                            ]
                        ),
                    ]
                )
            ),

            new OA\Response(
                response: 404,
                description: 'Connector not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Connector not found with ID: 99'),
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
        $connector = $this->connectorService->getConnectorById($id);

        return ApiResponse::success(
            'Connector retrieved successfully.',
            200,
            new ConnectorResource($connector)
        );
    }

    #[OA\Put(
        path: '/api/connectors/{id}',
        tags: ['Connectors'],
        summary: 'Update a connector',
        description: 'Updates the name, allowed events, and active status. The token and organization cannot be changed.',
        operationId: 'updateConnector',
        parameters: [
            new OA\Parameter(name: 'id',in: 'path',required: true,description: 'ID of the connector to update.',schema: new OA\Schema(type: 'integer', example: 1))
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'active'],
                properties: [
                    new OA\Property(property: 'name',type: 'string',minLength: 3,maxLength: 255,description: 'New name. Must be unique within the organization.',example: 'Updated POS System'),
                    new OA\Property(property: 'allowed_events',type: 'array',nullable: true,description: 'Updated list of allowed event types. Send null to allow all events.',items: new OA\Items(type: 'string', example: 'invoice.created')),
                    new OA\Property(property: 'active',type: 'boolean',description: 'Whether the connector is active.',example: true)
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Connector updated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Connector updated successfully.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'organization_id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'Updated POS System'),
                                new OA\Property(property: 'active', type: 'boolean', example: true),
                                new OA\Property(property: 'allowed_events',type: 'array',nullable: true,items: new OA\Items(type: 'string', example: 'invoice.created')),
                                new OA\Property(property: 'last_used_at',type: 'string',format: 'date-time',nullable: true,example: null),
                                new OA\Property(property: 'created_at',type: 'string',format: 'date-time',example: '2026-05-19T08:00:00.000000Z')
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Connector not found',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Connector not found with ID: 99'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items())
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
                                new OA\Property(property: 'name',type: 'array',items: new OA\Items(type: 'string',example: 'The name field is required.'))
                            ]
                        )
                    ]
                )
            ),
            new OA\Response(
                response: 409,
                description: 'Conflict — connector name already exists in this organization',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message',type: 'string',example: 'A connector with the same name already exists in this organization.'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items())
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
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items())
                    ]
                )
            )
        ]
    )]
    public function update(int $id, UpdateConnectorRequest $request): JsonResponse
    {
        $data      = $request->safe()->only(['name', 'allowed_events', 'active']);
        $connector = $this->connectorService->updateConnector($id, $data);

        return ApiResponse::success(
            'Connector updated successfully.',
            200,
            new ConnectorResource($connector)
        );
    }

    #[OA\Delete(
        path: "/api/connectors/{id}",
        tags: ["Connectors"],
        summary: "Delete a connector",
        description: "Permanently deletes a connector. Existing integration events referencing this connector will also be removed (cascade).",
        operationId: "destroyConnector",
        parameters: [
            new OA\Parameter(name: "id",in: "path",required: true,description: "ID of the connector to delete.",schema: new OA\Schema(type: "integer", example: 1))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Connector deleted successfully",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: true),
                        new OA\Property(property: "message", type: "string", example: "Connector deleted successfully."),
                        new OA\Property(property: "data",type: "array",items: new OA\Items())
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: "Connector not found",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "Connector not found with ID: 99"),
                        new OA\Property(property: "data",type: "array",items: new OA\Items())
                    ]
                )
            ),
            new OA\Response(
                response: 500,
                description: "Internal server error",
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: "success", type: "boolean", example: false),
                        new OA\Property(property: "message", type: "string", example: "A database error occurred."),
                        new OA\Property(property: "data",type: "array",items: new OA\Items())
                    ]
                )
            )
        ]
    )]
    public function destroy(int $id): JsonResponse
    {
        $this->connectorService->deleteConnector($id);

        return ApiResponse::success('Connector deleted successfully.', 200);
    }
}