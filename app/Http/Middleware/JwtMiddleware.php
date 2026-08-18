<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Symfony\Component\HttpFoundation\Response;

class JwtMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $user = JWTAuth::parseToken()->authenticate();

            if (!$user) {
                return ApiResponse::error('User not found', 401);
            }

            $request->attributes->set('auth_user', $user);
        } catch (TokenExpiredException $e) {
            return ApiResponse::error('Token has expired', 401);
        } catch (TokenInvalidException $e) {
            return ApiResponse::error('Token is invalid', 401);
        } catch (JWTException $e) {
            return ApiResponse::error('Token not provided', 401);
        } catch (\Exception $e) {
            return ApiResponse::error('Authorization error', 401);
        }

        return $next($request);
    }
}