<?php

namespace App\Modules\Integration\Xero\Controller;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessXeroWebhookEventJob;
use App\Modules\Integration\Xero\Service\XeroWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Xero Webhooks',
    description: 'Webhook receiver — authenticated by HMAC signature, not Bearer token.'
)]
class XeroWebhookController extends Controller
{
    public function __construct(
        private readonly XeroWebhookService $webhookService,
    ){

    }

    #[OA\Post(
        path: '/api/integrations/xero/webhook',
        tags: ['Xero Webhooks'],
        summary: 'Receive Xero webhook notifications',
        description: 'Receives Xero webhook events. The request must carry an `x-xero-signature` header computed as `base64(HMAC-SHA256(raw_body, XERO_WEBHOOK_KEY))`. Returns 200 within 5s and processes events asynchronously via ProcessXeroWebhookEventJob.',
        operationId: 'xeroWebhookReceive',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'events', type: 'array', items: new OA\Items(type: 'object')),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Webhook accepted (events queued).'),
            new OA\Response(response: 401, description: 'Missing or invalid x-xero-signature.'),
        ]
    )]
    public function receive(Request $request): JsonResponse
    {
        if (!$this->webhookService->isValidSignature($request))
            return response()->json([], 401);

        foreach ($request->input('events', []) as $event) {
            ProcessXeroWebhookEventJob::dispatch($event)->onQueue('default');
        }

        return response()->json([], 200);
    }
}