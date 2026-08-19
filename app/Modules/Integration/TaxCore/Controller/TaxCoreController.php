<?php

namespace App\Modules\Integration\TaxCore\Controller;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Integration\TaxCore\Service\TaxCoreConnectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'TaxCore Integration',
    description: 'Endpoints for managing the TaxCore fiscal integration. The nexo-agent daemon holds the certificate and PAC on the client machine and calls V-SDC directly.'
)]
class TaxCoreController extends Controller
{
    public function __construct(
        private readonly TaxCoreConnectionService $connectionService,
    ) {}

    #[OA\Post(
        path: '/api/integrations/taxcore/connect-agent',
        tags: ['TaxCore Integration'],
        summary: 'Register a TaxCore connection in agent mode',
        description: 'Creates a TaxCore connection record without storing the certificate or PAC on the server. The nexo-agent daemon holds them securely in the OS keychain (DPAPI on Windows) and calls V-SDC directly via mTLS.',
        operationId: 'taxcoreConnectAgent',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['organization_id', 'environment'],
                properties: [
                    new OA\Property(property: 'organization_id', type: 'integer', example: 1),
                    new OA\Property(property: 'environment', type: 'string', enum: ['sandbox', 'production'], example: 'sandbox'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Agent connection registered.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'TaxCore agent connection registered.'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'environment', type: 'string', example: 'sandbox'),
                                new OA\Property(property: 'active', type: 'boolean', example: true),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error.'),
        ]
    )]
    public function connectAgent(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'organization_id' => 'required|integer|exists:organizations,id',
            'environment' => 'required|string|in:sandbox,production',
        ]);

        $connection = $this->connectionService->connectAgent(
            $validated['organization_id'],
            $validated['environment'],
        );

        return ApiResponse::success('TaxCore agent connection registered.', 200, [
            'id' => $connection->id,
            'environment' => $connection->getEnvironment(),
            'active' => $connection->getActive(),
        ]);
    }
}
