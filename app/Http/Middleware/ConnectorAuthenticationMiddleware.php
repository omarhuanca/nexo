<?php

namespace App\Http\Middleware;

use App\Events\Auth\ConnectorAuthFailed;
use App\Events\Auth\ConnectorAuthenticated;
use App\Http\Responses\ApiResponse;
use App\Modules\Connector\Repository\ConnectorRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConnectorAuthenticationMiddleware
{
    public function __construct(private readonly ConnectorRepository $connectorRepository){}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $ip = $request->ip();
        $userAgent = substr((string) $request->userAgent(), 0, 200);

        if (!$token) {
            event(new ConnectorAuthFailed('missing_token', null, $ip, $userAgent));
            return ApiResponse::error('Unauthorized', 401);
        }

        $hashedToken = hash('sha256', $token);
        $tokenPrefix = substr($token, 0, 8);

        try {
            $connector = $this->connectorRepository->findByTokenHash($hashedToken);
        } catch (\Exception $e) {
            event(new ConnectorAuthFailed('lookup_error', $tokenPrefix, $ip, $userAgent));
            return ApiResponse::error('Unauthorized', 401);
        }

        if (!$connector) {
            event(new ConnectorAuthFailed('invalid_token', $tokenPrefix, $ip, $userAgent));
            return ApiResponse::error('Unauthorized', 401);
        }

        if (!$connector->active) {
            event(new ConnectorAuthFailed('inactive_connector', $tokenPrefix, $ip, $userAgent));
            return ApiResponse::error('Your connection is inactive', 403);
        }

        event(new ConnectorAuthenticated(
            $connector->getId(),
            $connector->getOrganizationId(),
            $ip,
            $userAgent,
        ));

        $connector->last_used_at = now();
        $this->connectorRepository->save($connector);
        $request->attributes->set('connector', $connector);

        return $next($request);
    }
}
