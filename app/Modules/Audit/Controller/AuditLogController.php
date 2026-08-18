<?php

namespace App\Modules\Audit\Controller;

use App\Http\Controllers\Controller;
use App\Http\Requests\AuditLogRequest;
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
    public function __construct(private readonly AuditLogService $service) {}

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
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'date', type: 'string', format: 'date'),
                                new OA\Property(property: 'count', type: 'integer'),
                                new OA\Property(property: 'entries', type: 'array', items: new OA\Items(type:'object')),
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
            $date = $request->validated('date');
            $entries = $this->service->getLogByDate($date);

            return ApiResponse::success(
                'Audit log retrieved successfully.',
                200,
                [
                    'date' => $date,
                    'count' => count($entries),
                    'entries' => $entries
                ]
            );
        } catch (NotFoundException) {
            return ApiResponse::notFound('Audit log');
        }
    }
}
