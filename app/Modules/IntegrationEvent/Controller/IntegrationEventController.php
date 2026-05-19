<?php

namespace App\Modules\IntegrationEvent\Controller;

use App\Http\Controllers\Controller;
use App\Http\Requests\IntegrationEventRequest;
use App\Http\Resources\IntegrationEventResource;
use App\Http\Responses\ApiResponse;
use App\Modules\IntegrationEvent\Service\IntegrationEventService;
use Illuminate\Http\JsonResponse;
use OpenApi\Annotations as OA;

/**
 * @OA\SecurityScheme(
 *     securityScheme="connectorToken",
 *     type="http",
 *     scheme="bearer",
 *     description="Bearer token issued when a Connector is created. Only visible at creation time."
 * )
 *
 * @OA\Tag(
 *     name="Integration Events",
 *     description="Endpoints for receiving integration events from external connectors."
 * )
 */
class IntegrationEventController extends Controller
{
    public function __construct(
        private readonly IntegrationEventService $integrationEventService
    ) {}

    /**
     * @OA\Post(
     *     path="/api/integration-events",
     *     tags={"Integration Events"},
     *     summary="Receive an integration event",
     *     description="Ingests an event sent by an external connector (e.g. a POS system). Requires the connector's Bearer token for authentication.",
     *     operationId="storeIntegrationEvent",
     *     security={{"connectorToken": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"event_type", "payload"},
     *             @OA\Property(
     *                 property="external_event_id",
     *                 type="string",
     *                 format="uuid",
     *                 nullable=true,
     *                 description="Optional UUID from the originating system, used for idempotency. Must be unique across all events.",
     *                 example="550e8400-e29b-41d4-a716-446655440000"
     *             ),
     *             @OA\Property(
     *                 property="event_type",
     *                 type="string",
     *                 maxLength=255,
     *                 description="Type of the event being sent (e.g. invoice.created).",
     *                 example="invoice.created"
     *             ),
     *             @OA\Property(
     *                 property="payload",
     *                 type="object",
     *                 description="Arbitrary JSON object containing the event data.",
     *                 example={"invoice_number": "F001-00012", "total": 250.00, "currency": "PEN"}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Event received and queued successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Event created successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=42),
     *                 @OA\Property(property="external_event_id", type="string", format="uuid", nullable=true, example="550e8400-e29b-41d4-a716-446655440000"),
     *                 @OA\Property(property="connector_id", type="integer", example=1),
     *                 @OA\Property(property="organization_id", type="integer", example=1),
     *                 @OA\Property(property="event_type", type="string", example="invoice.created"),
     *                 @OA\Property(property="payload", type="object", example={"invoice_number": "F001-00012", "total": 250.00}),
     *                 @OA\Property(property="status", type="string", example="pending"),
     *                 @OA\Property(property="attempts", type="integer", example=0),
     *                 @OA\Property(property="received_at", type="string", format="date-time", nullable=true, example="2026-05-19T10:00:00.000000Z"),
     *                 @OA\Property(property="processed_at", type="string", format="date-time", nullable=true, example=null),
     *                 @OA\Property(property="error_message", type="string", nullable=true, example=null)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated — missing or invalid connector token",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Unauthorized."),
     *             @OA\Property(property="data", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden — connector is inactive or event type is not allowed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="This connector is not allowed to send events of type invoice.created."),
     *             @OA\Property(property="data", type="array", @OA\Items())
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error — required fields missing or invalid",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation Error"),
     *             @OA\Property(property="errors", type="object",
     *                 @OA\Property(property="event_type", type="array", @OA\Items(type="string", example="The event type field is required.")),
     *                 @OA\Property(property="payload", type="array", @OA\Items(type="string", example="The payload field is required."))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Internal server error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="A database error occurred."),
     *             @OA\Property(property="data", type="array", @OA\Items())
     *         )
     *     )
     * )
     */
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