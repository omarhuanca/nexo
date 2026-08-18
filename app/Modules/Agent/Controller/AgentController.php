<?php

namespace App\Modules\Agent\Controller;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Agent\Domain\AgentToken;
use App\Modules\Agent\Service\AgentTokenService;
use App\Modules\Integration\TaxCore\Service\TaxCoreSaleService;
use App\Modules\Sale\Domain\Sale;
use App\Modules\Sale\Repository\SaleRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\SecurityScheme(
    securityScheme: 'agentToken',
    type: 'http',
    scheme: 'bearer',
    description: 'Bearer token issued by POST /api/agent/token. Used by the nexo-agent daemon to authenticate all agent endpoints.'
)]
#[OA\Tag(
    name: 'Agent',
    description: 'Endpoints for the nexo-agent desktop daemon. The daemon connects via WebSocket (Reverb) and receives fiscalization tasks pushed by the server.'
)]
class AgentController extends Controller
{
    public function __construct(
        private readonly AgentTokenService $agentTokenService,
        private readonly SaleRepository $saleRepository,
        private readonly TaxCoreSaleService $taxCoreSaleService,
    ) {}

    #[OA\Post(
        path: '/api/agent/token',
        tags: ['Agent'],
        security: [['bearerAuth' => []]],
        summary: 'Create an agent token for an organization',
        description: 'Generates a one-time-visible Bearer token that the nexo-agent daemon uses to authenticate. Store it immediately — it cannot be recovered.',
        operationId: 'agentCreateToken',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['organization_id'],
                properties: [
                    new OA\Property(property: 'organization_id', type: 'integer', example: 1, description: 'ID of the organization the agent will represent.'),
                    new OA\Property(property: 'name', type: 'string', example: 'nexo-agent-laptop', description: 'Optional label to identify this token (max 100 chars).'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Token created. Copy the token value — it will not be shown again.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Agent token created'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'token', type: 'string', example: 'a1b2c3d4e5f6...', description: 'Plain-text token. Pass as Bearer in Authorization header. Only shown once.'),
                                new OA\Property(property: 'agent_token_id', type: 'integer', example: 1, description: 'Internal ID of the created token record.'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 422, description: 'Validation error — organization_id missing or not found.'),
        ]
    )]
    public function createToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'organization_id' => 'required|integer|exists:organizations,id',
            'name' => 'sometimes|string|max:100',
        ]);

        $result = $this->agentTokenService->createToken(
            $validated['organization_id'],
            $validated['name'] ?? 'nexo-agent',
        );

        return ApiResponse::created('Agent token created', $result);
    }

    #[OA\Get(
        path: '/api/agent/config',
        tags: ['Agent'],
        summary: 'Fetch Reverb connection parameters',
        description: 'Called by the daemon at startup to get the WebSocket server details. This way the agent only needs the nexo_url and its token — no infrastructure details need to be distributed manually.',
        operationId: 'agentConfig',
        security: [['agentToken' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Reverb connection parameters.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'OK'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'reverb_app_key', type: 'string', example: 'drt0nv5lmajiwoj2a58h'),
                                new OA\Property(property: 'reverb_host',    type: 'string', example: 'localhost'),
                                new OA\Property(property: 'reverb_port',    type: 'integer', example: 8080),
                                new OA\Property(property: 'reverb_scheme',  type: 'string', example: 'http'),
                                new OA\Property(property: 'organization_id', type: 'integer', example: 1),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Invalid agent token.'),
        ]
    )]
    public function config(Request $request): JsonResponse
    {
        /** @var AgentToken $agentToken */
        $agentToken = $request->attributes->get('agent_token');

        return ApiResponse::success('OK', 200, [
            'reverb_app_key' => config('broadcasting.connections.reverb.key'),
            'reverb_host' => config('broadcasting.connections.reverb.options.host'),
            'reverb_port' => (int) config('broadcasting.connections.reverb.options.port'),
            'reverb_scheme' => config('broadcasting.connections.reverb.options.scheme'),
            'organization_id' => $agentToken->getOrganizationId(),
        ]);
    }

    #[OA\Post(
        path: '/api/agent/ping',
        tags: ['Agent'],
        summary: 'Heartbeat — marks the agent as online',
        description: 'The daemon calls this every 15 seconds. The server uses the last_seen_at timestamp to decide whether to push tasks via WebSocket or queue them as pending_fiscal.',
        operationId: 'agentPing',
        security: [['agentToken' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Pong — last_seen_at updated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'pong'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(), example: []),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Invalid or expired agent token.'),
        ]
    )]
    public function ping(Request $request): JsonResponse
    {
        /** @var AgentToken $agentToken */
        $agentToken = $request->attributes->get('agent_token');
        $this->agentTokenService->touch($agentToken);

        return ApiResponse::success('pong');
    }

    #[OA\Get(
        path: '/api/agent/pending',
        tags: ['Agent'],
        summary: 'Reconciliation — get pending fiscal tasks',
        description: 'Called by the agent immediately after reconnecting to Reverb. Returns all sales with status=pending_fiscal for this organization, with their TaxCore-formatted payloads ready to send to V-SDC.',
        operationId: 'agentPending',
        security: [['agentToken' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of pending fiscalization tasks.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Pending fiscal tasks'),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'task_id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
                                    new OA\Property(property: 'sale_id', type: 'integer', example: 42),
                                    new OA\Property(property: 'method', type: 'string', example: 'POST'),
                                    new OA\Property(property: 'endpoint', type: 'string', example: '/api/v3/invoices'),
                                    new OA\Property(property: 'payload', type: 'object', description: 'TaxCore-formatted invoice payload ready to send to V-SDC.'),
                                ]
                            )
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Invalid or expired agent token.'),
        ]
    )]
    public function pending(Request $request): JsonResponse
    {
        /** @var AgentToken $agentToken */
        $agentToken = $request->attributes->get('agent_token');
        $orgId = $agentToken->getOrganizationId();

        $sales = $this->saleRepository->findPendingFiscalByOrganization($orgId);

        $tasks = array_map(function (Sale $sale) {
            return [
                'task_id' => (string) Str::uuid(),
                'sale_id' => $sale->id,
                'method' => 'POST',
                'endpoint' => '/api/v3/invoices',
                'payload' => $this->taxCoreSaleService->buildPayload($sale->getPayload()),
            ];
        }, $sales);

        return ApiResponse::success('Pending fiscal tasks', 200, $tasks);
    }

    #[OA\Post(
        path: '/api/agent/result',
        tags: ['Agent'],
        summary: 'Report a fiscalization result',
        description: 'After the agent calls V-SDC and gets a response (or an error), it POSTs the result here. The server marks the sale as completed or failed accordingly. Idempotent: if the sale is already completed, the request is ignored.',
        operationId: 'agentResult',
        security: [['agentToken' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['sale_id', 'task_id', 'ok'],
                properties: [
                    new OA\Property(property: 'sale_id', type: 'integer', example: 42, description: 'ID of the sale being fiscalized.'),
                    new OA\Property(property: 'task_id', type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000', description: 'Task UUID from the original fiscalization.requested event.'),
                    new OA\Property(property: 'ok', type: 'boolean', example: true, description: 'true if V-SDC returned a successful response, false on error.'),
                    new OA\Property(property: 'fiscal_number', type: 'string', nullable: true, example: 'CPLP77KX-Dt1Ov1o0-5', description: 'invoiceNumber from V-SDC response. Required when ok=true.'),
                    new OA\Property(property: 'fiscal_result', type: 'object', nullable: true, description: 'Full JSON body returned by V-SDC. Stored as-is for audit.'),
                    new OA\Property(property: 'error', type: 'string', nullable: true, example: 'V-SDC returned HTTP 400', description: 'Error message. Required when ok=false.'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Result recorded. Sale status updated.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Result recorded'),
                        new OA\Property(property: 'data', type: 'array', items: new OA\Items(), example: []),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Invalid or expired agent token.'),
            new OA\Response(response: 403, description: 'The sale does not belong to the agent\'s organization.'),
            new OA\Response(response: 422, description: 'Validation error.'),
        ]
    )]
    public function result(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sale_id' => 'required|integer|exists:sales,id',
            'task_id' => 'required|string',
            'ok' => 'required|boolean',
            'fiscal_number' => 'nullable|string',
            'fiscal_result' => 'nullable|array',
            'error' => 'nullable|string',
        ]);

        /** @var AgentToken $agentToken */
        $agentToken = $request->attributes->get('agent_token');

        $sale = $this->saleRepository->findByIdForOrganization(
            $validated['sale_id'],
            $agentToken->getOrganizationId(),
        );

        if ($sale->getStatus() === 'completed') return ApiResponse::success('Already completed');

        if ($validated['ok']) {
            $sale->setFiscalNumber($validated['fiscal_number'] ?? null);
            $sale->setFiscalResult($validated['fiscal_result'] ?? []);
            $sale->setStatus('completed');
            $sale->setProcessedAt(now());
            $sale->setErrorMessage(null);
        } else {
            $sale->setStatus('failed');
            $sale->setErrorMessage($validated['error'] ?? 'Agent fiscalization failed');
        }

        $this->saleRepository->save($sale);

        return ApiResponse::success('Result recorded');
    }

    #[OA\Post(
        path: '/api/agent/broadcasting-auth',
        tags: ['Agent'],
        summary: 'Authenticate Reverb private channel',
        description: 'Called by the nexo-agent daemon after connecting to the Reverb WebSocket. Validates the agent token and returns a Pusher HMAC signature so the daemon can subscribe to private-agent.{org_id}.',
        operationId: 'agentBroadcastingAuth',
        security: [['agentToken' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['socket_id', 'channel_name'],
                    properties: [
                        new OA\Property(property: 'socket_id', type: 'string', example: '123.456', description: 'Socket ID provided by Reverb in the pusher:connection_established event.'),
                        new OA\Property(property: 'channel_name', type: 'string', example: 'private-agent.1', description: 'Must be private-agent.{organization_id} matching the token\'s org.'),
                    ]
                )
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Auth signature generated. Use the auth value in pusher:subscribe.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'OK'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'auth', type: 'string', example: 'nexo-local-key:a1b2c3d4...', description: 'Pusher auth string: appKey:HMAC-SHA256(socketId:channel).'),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Invalid agent token.'),
            new OA\Response(response: 403, description: 'channel_name does not match this token\'s organization.'),
        ]
    )]
    public function broadcastingAuth(Request $request): JsonResponse
    {
        /** @var AgentToken $agentToken */
        $agentToken = $request->attributes->get('agent_token');

        $socketId = $request->input('socket_id', '');
        $channelName = $request->input('channel_name', '');

        if ($channelName !== "private-agent.{$agentToken->getOrganizationId()}") {
            return ApiResponse::error('Forbidden', 403);
        }

        $secret = config('broadcasting.connections.reverb.secret');
        $appKey = config('broadcasting.connections.reverb.key');
        $signature = hash_hmac('sha256', "{$socketId}:{$channelName}", $secret);

        return ApiResponse::success('OK', 200, ['auth' => "{$appKey}:{$signature}"]);
    }
}