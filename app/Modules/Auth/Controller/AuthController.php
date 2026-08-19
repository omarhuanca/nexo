<?php

namespace App\Modules\Auth\Controller;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Modules\Auth\Requests\LoginRequest;
use App\Modules\Auth\Service\AuthService;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use RuntimeException;

#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
    description: 'Bearer JWT issued by POST /api/auth/login. Pass as: Authorization: Bearer <token>'
)]
#[OA\Tag(
    name: 'Authentication',
    description: 'Admin authentication endpoints. Login returns a JWT containing scopes that authorize access to admin endpoints.'
)]
class AuthController extends Controller
{
    public function __construct(private readonly AuthService $authService) {}

    #[OA\Post(
        path: '/api/auth/login',
        tags: ['Authentication'],
        summary: 'Admin login',
        description: 'Authenticates an admin user with email and password, returns a JWT with the user scopes.',
        operationId: 'authLogin',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'admin' . '@' . 'nexo' . '.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'Password1!'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Login successful.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Login successful'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'access_token', type: 'string', example: 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...'),
                                new OA\Property(property: 'token_type', type: 'string', example: 'bearer'),
                                new OA\Property(property: 'expires_in', type: 'integer', example: 3600),
                                new OA\Property(
                                    property: 'user',
                                    type: 'object',
                                    properties: [
                                        new OA\Property(property: 'id', type: 'integer', example: 1),
                                        new OA\Property(property: 'name', type: 'string', example: 'Administrator'),
                                        new OA\Property(property: 'email', type: 'string', example: 'admin@nexo.com'),
                                        new OA\Property(property: 'scopes', type: 'array', items: new OA\Items(type: 'string', example: '*:*')),
                                    ]
                                ),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Invalid credentials.'),
            new OA\Response(response: 422, description: 'Validation error.'),
        ]
    )]
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login(
                $request->input('email'),
                $request->input('password'),
            );

            return ApiResponse::success('Login successful', 200, $result);
        } catch (RuntimeException $e) {
            return ApiResponse::error($e->getMessage(), 401);
        }
    }

    #[OA\Post(
        path: '/api/auth/refresh',
        tags: ['Authentication'],
        summary: 'Refresh JWT token',
        description: 'Exchanges the current (expired) token for a new one. The new token carries the same scopes as the old one.',
        operationId: 'authRefresh',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Token refreshed.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Token refreshed'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'access_token', type: 'string'),
                                new OA\Property(property: 'token_type', type: 'string', example: 'bearer'),
                                new OA\Property(property: 'expires_in', type: 'integer', example: 3600),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Invalid or expired token.'),
        ]
    )]
    public function refresh(): JsonResponse
    {
        try {
            $result = $this->authService->refresh();
            return ApiResponse::success('Token refreshed', 200, $result);
        } catch (TokenExpiredException $e) {
            return ApiResponse::error('Token cannot be refreshed', 401);
        } catch (TokenInvalidException $e) {
            return ApiResponse::error('Invalid token', 401);
        } catch (JWTException $e) {
            return ApiResponse::error('Token not provided', 401);
        }
    }

    #[OA\Get(
        path: '/api/auth/me',
        tags: ['Authentication'],
        summary: 'Get authenticated admin',
        description: 'Returns the authenticated admin user with their scopes.',
        operationId: 'authMe',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Authenticated admin retrieved.',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'success', type: 'boolean', example: true),
                        new OA\Property(property: 'message', type: 'string', example: 'Authenticated user'),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'Administrator'),
                                new OA\Property(property: 'email', type: 'string', example: 'admin@nexo.com'),
                                new OA\Property(property: 'scopes', type: 'array', items: new OA\Items(type: 'string', example: '*:*')),
                            ]
                        ),
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Not authenticated.'),
        ]
    )]
    public function me(): JsonResponse
    {
        try {
            $user = $this->authService->me();

            return ApiResponse::success('Authenticated user', 200, [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'scopes' => $user->scopes ?? [],
            ]);
        } catch (JWTException $e) {
            return ApiResponse::error('Not authenticated', 401);
        }
    }

    #[OA\Post(
        path: '/api/auth/logout',
        tags: ['Authentication'],
        summary: 'Logout — invalidate current token',
        description: 'Invalidates the current JWT so it can no longer be used.',
        operationId: 'authLogout',
        security: [['bearerAuth' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Logged out.'),
            new OA\Response(response: 401, description: 'Not authenticated.'),
        ]
    )]
    public function logout(): JsonResponse
    {
        try {
            $this->authService->logout();
            return ApiResponse::success('Logged out', 200);
        } catch (JWTException $e) {
            return ApiResponse::error('Not authenticated', 401);
        }
    }
}