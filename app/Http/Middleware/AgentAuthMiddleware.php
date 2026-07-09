<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Modules\Agent\Service\AgentTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AgentAuthMiddleware
{
    public function __construct(private readonly AgentTokenService $agentTokenService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->bearerToken();

        if (!$plain) return ApiResponse::error('Unauthorized', 401);

        $agentToken = $this->agentTokenService->authenticate($plain);

        if (!$agentToken || !$agentToken->getActive()) return ApiResponse::error('Unauthorized', 401);
        

        $request->attributes->set('agent_token', $agentToken);

        return $next($request);
    }
}