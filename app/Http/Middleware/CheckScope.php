<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

class CheckScope
{
    public function handle(Request $request, Closure $next, string $requiredScope): Response
    {
        $user = JWTAuth::user();

        if (!$user) {
            return ApiResponse::error('Not authenticated', 401);
        }

        if (!$user->hasScope($requiredScope)) {
            return ApiResponse::error(
                "Insufficient permissions. Required scope: {$requiredScope}",
                403
            );
        }

        return $next($request);
    }
}