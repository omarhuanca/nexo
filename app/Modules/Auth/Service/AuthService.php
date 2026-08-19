<?php

namespace App\Modules\Auth\Service;

use App\Modules\User\Domain\User;
use App\Modules\User\Service\UserService;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenInvalidException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use RuntimeException;

class AuthService
{
    public function __construct(private readonly UserService $userService) {}

    public function login(string $email, string $password): array
    {
        $credentials = ['email' => $email, 'password' => $password];

        if (!$token = JWTAuth::attempt($credentials)) {
            throw new RuntimeException('Invalid credentials');
        }

        /** @var User $user */
        $user = JWTAuth::user();

        if (!$user->active) {
            JWTAuth::invalidate($token);
            throw new RuntimeException('User account is inactive.');
        }

        $this->userService->recordLogin($user);

        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'scopes' => $user->scopes ?? [],
            ],
        ];
    }

    public function refresh(): array
    {
        $newToken = JWTAuth::parseToken()->refresh();

        return [
            'access_token' => $newToken,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 60,
        ];
    }

    public function me(): User
    {
        return JWTAuth::user();
    }

    public function logout(): void
    {
        JWTAuth::parseToken()->invalidate();
    }
}