<?php

namespace App\Modules\IntegrationEvent\Controller;

use App\Http\Controllers\Controller;
use App\Http\Requests\IntegrationEventRequest;
use App\Http\Resources\IntegrationEventResource;
use App\Http\Responses\ApiResponse;
use App\Modules\IntegrationEvent\Service\IntegrationEventService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\SecurityScheme(
    securityScheme: 'connectorToken',
    type: 'http',
    scheme: 'bearer',
    description: 'Bearer token issued when a Connector is created. Only visible at creation time.'
)]
#[OA\Tag(
    name: 'Integration Events',
    description: 'Endpoints for receiving integration events from external connectors.'
)]
class IntegrationEventController extends Controller
{
    public function __construct(
        private readonly IntegrationEventService $integrationEventService
    ) {}

    #[OA\Post(
        path: '/api/integration-events',
        tags: ['Integration Events'],
        summary: 'Receive an integration event',
        description: "Ingests an event sent by an external connector (e.g. a POS system). Requires the connector's Bearer token for authentication.",
        operationId: 'storeIntegrationEvent',
        security: [['connectorToken' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['event_type', 'payload'],
                properties: [
                    new OA\Property(
                        property: 'external_event_id',
                        type: 'string',
                        format: 'uuid',
                        nullable: true,
                        description: 'Optional UUID from the originating system, used for idempotency. Must be unique across all events.',
                        example: '550e8400-e29b-41d4-a716-446655440000'
                    ),
                    new OA\Property(
                        property: 'event_type',
                        type: 'string',
                        maxLength: 255,
                        description: 'Type of the event being sent (e.g. invoice.created).',
                        example: 'invoice.created'
                    ),
                    new OA\Property(
                        property: 'payload',
                        type: 'object',
                        description: 'Arbitrary JSON object containing the event data.',
                        example: [
                            'invoice_number' => 'F001-00012',
                            'total' => 250.00,
                            'currency' => 'PEN'
                        ]
                    )
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Event received and queued successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Event created successfully'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 42),
                                new OA\Property(property: 'external_event_id', type: 'string', format: 'uuid', nullable: true, example: '550e8400-e29b-41d4-a716-446655440000'),
                                new OA\Property(property: 'connector_id', type: 'integer', example: 1),
                                new OA\Property(property: 'organization_id', type: 'integer', example: 1),
                                new OA\Property(property: 'event_type', type: 'string', example: 'invoice.created'),
                                new OA\Property(
                                    property: 'payload',
                                    type: 'object',
                                    example: [
                                        'invoice_number' => 'F001-00012',
                                        'total' => 250.00
                                    ]
                                ),
                                new OA\Property(property: 'status', type: 'string', example: 'pending'),
                                new OA\Property(property: 'attempts', type: 'integer', example: 0),
                                new OA\Property(property: 'received_at', type: 'string', format: 'date-time', nullable: true, example: '2026-05-19T10:00:00.000000Z'),
                                new OA\Property(property: 'processed_at', type: 'string', format: 'date-time', nullable: true, example: null),
                                new OA\Property(property: 'error_message', type: 'string', nullable: true, example: null),
                            ]
                        ),
                    ]
                )
            ),

            new OA\Response(
                response: 401,
                description: 'Unauthenticated — missing or invalid connector token',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Unauthorized.'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items())
                    ]
                )
            ),

            new OA\Response(
                response: 403,
                description: 'Forbidden — connector is inactive or event type is not allowed',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'This connector is not allowed to send events of type invoice.created.'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items())
                    ]
                )
            ),

            new OA\Response(
                response: 422,
                description: 'Validation error — required fields missing or invalid',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: false),
                        new OA\Property(property: 'message', type: 'string', example: 'Validation Error'),
                        new OA\Property(
                            property: 'errors',
                            type: 'object',
                            properties: [
                                new OA\Property(
                                    property: 'event_type',
                                    type: 'array',
                                    items: new OA\Items(type: 'string', example: 'The event type field is required.')
                                ),
                                new OA\Property(
                                    property: 'payload',
                                    type: 'array',
                                    items: new OA\Items(type: 'string', example: 'The payload field is required.')
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
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items())
                    ]
                )
            ),
        ]
    )]
    public function store(IntegrationEventRequest $request): JsonResponse
    {
        $connector = $request->attributes->get('connector');
        $data      = $request->validated();

        $event = $this->integrationEventService->createEvent(
            $connector,
            $data['external_event_id'] ?? null,
            $data['event_type'],
            $data['payload']
        );

        return ApiResponse::created(
            'Event created successfully',
            new IntegrationEventResource($event)
        );
    }
}