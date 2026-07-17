<?php

namespace App\Modules\Integration\Xero\Controller;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessXeroWebhookEventJob;
use App\Modules\Integration\Xero\Service\XeroWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class XeroWebhookController extends Controller
{
    public function __construct(
        private readonly XeroWebhookService $webhookService,
    ){

    }

    /**
     * Xero requires a 200 within 5 seconds or the delivery (including the
     * "intent to receive" validation) is marked failed. So this only
     * validates the signature and hands each event off to a queued job —
     * it never calls Xero or touches the DB inline.
     */
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