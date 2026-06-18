<?php

namespace App\Modules\Integration\Xero\Controller;

use App\Http\Controllers\Controller;
use App\Modules\Integration\Xero\Service\XeroConnectionService;
use App\Modules\Integration\Xero\Service\XeroWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class XeroWebhookController extends Controller
{
    public function __construct(
        private readonly XeroWebhookService $webhookService,
        private readonly XeroConnectionService $connectionService
    ){

    }
    public function receive(Request $request): JsonResponse
    {
        if (!$this->webhookService->isValidSignature($request))
            return response()->json([], 401);
        
        foreach ($request->input('events', []) as $event) {
            $data = $this->webhookService->getData(
                $event['eventCategory'],
                $event['resourceId'],
                $event['tenantId']
            );

            
            logger()->info($data);
        }

        return response()->json([], 200);
    }
}