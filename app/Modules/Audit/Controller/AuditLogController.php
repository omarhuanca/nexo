<?php

namespace App\Modules\Audit\Controller;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLogRequest;
use App\Http\Resources\AuditLogResource;
use App\Http\Responses\ApiResponse;
use App\Modules\Audit\Service\AuditLogService;
use App\Shared\Exceptions\NotFoundException;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;


#[OA\Tag(
    name: 'Audit Logs',
    description: 'Endpoints for retrieving audit logs from storage.'
)]
class AuditLogController extends Controller
{
    private AuditLogService $service;

    public function __construct(AuditLogService $service) {
        $this->service = $service;
    }

    #[OA\Get(
        path: '/api/audit-logs',
        tags: ['Audit Logs'],
        summary: 'Get audit log by date',
        description: 'Returns audit log entries for the specified date. Date format: YYYY-MM-DD',
        operationId: 'getAuditLog',
        parameters: [
            new OA\Parameter(
                name: 'date',
                in: 'query',
                required: true,
                description: 'Log date in format YYYY-MM-DD',
                schema: new OA\Schema(type: 'string', format: 'date', example: '2026-08-17')
            ),
            new OA\Parameter(
                name: 'page',
                in: 'query',
                required: false,
                description: 'Page number. Defaults to 1.',
                schema: new OA\Schema(type: 'integer', minimum: 1, default: 1, example: 1)
            ),
            new OA\Parameter(
                name: 'perPage',
                in: 'query',
                required: false,
                description: 'Number of entries per page. Defaults to 15, maximum 100.',
                schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 15, example: 15)
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Audit log retrieved successfully.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(type: 'object')
                        ),
                        new OA\Property(
                            property: 'pagination',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'current_page', type: 'integer'),
                                new OA\Property(property: 'last_page', type: 'integer'),
                                new OA\Property(property: 'per_page', type: 'integer'),
                                new OA\Property(property: 'total', type: 'integer'),
                                new OA\Property(property: 'from', type: 'integer', nullable: true),
                                new OA\Property(property: 'to', type: 'integer', nullable: true),
                            ]
                        ),
                    ]
                ),

            ),
            new OA\Response(response: 422, description: 'Validation error - date must use YYYY-MM-DD format.'),
            new OA\Response(response: 404, description: 'No audit log found for the specified date.'),
            new OA\Response(response: 500, description: 'Audit log contains invalid JSON or cannot be read.')
        ]
    )]
    public function show(AuditLogRequest $request): JsonResponse
    {
        try {
            $paginator = $this->service->paginateLogByDate(
                $request->validated('date'),
                $request->integer('page', 1),
                $request->integer('perPage', 15)
            );

            return ApiResponse::paginated(
                'Audit log retrieved successfully.',
                $paginator,
                AuditLogResource::class
            );
        } catch (NotFoundException) {
            return ApiResponse::notFound('Audit log');
        }
    }
}
