<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Modules\Connector\Repository\ConnectorRepository;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConnectorAuthenticationMiddleware
{
    public function __construct(private readonly ConnectorRepository $connectorRepository){}
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if(!$token) return ApiResponse::error('Unauthorized', 401);

        $hashedToken = hash('sha256', $token);

        try{
            $connector = $this->connectorRepository->findByTokenHash($hashedToken);
        } catch (\Exception $e) {
            return ApiResponse::error('Unauthorized', 401);
        }

        if(!$connector) return ApiResponse::error('Unauthorized', 401);
        if(!$connector->active) return ApiResponse::error('Your connection is inactive', 403);

        $connector->last_used_at = now();

        $this->connectorRepository->save($connector);
        $request->attributes->set('connector', $connector);

        return $next($request);
    }
}
