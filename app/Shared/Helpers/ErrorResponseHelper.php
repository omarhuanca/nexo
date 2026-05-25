<?php

namespace App\Shared\Helpers;

use App\Shared\Exceptions\BusinessConflictException;
use Illuminate\Http\Client\Response;

class ErrorResponseHelper
{
    public static function handleErrors(Response $response): void
    {
        if ($response->successful())return;
    
        match ($response->status()) {
            400 => throw new BusinessConflictException('Invalid request sended: ' . $response->body(), 400),
            401 => throw new BusinessConflictException('Authentication failed.', 401),
            403 => throw new BusinessConflictException('Access denied.', 403),
            404 => throw new BusinessConflictException('Resource not found.', 404),
            429 => throw new BusinessConflictException('Rate limit exceeded.', 429),
            default => throw new BusinessConflictException('Unexpected API error: ' . $response->body(), $response->status()),
        };
    }
}