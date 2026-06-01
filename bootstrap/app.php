<?php

use App\Shared\Exceptions\BusinessConflictException;
use App\Shared\Exceptions\DomainValidationException;
use App\Shared\Exceptions\NotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use App\Http\Responses\ApiResponse;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'connector.auth' => App\Http\Middleware\ConnectorAuthenticationMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $e) {
            return ApiResponse::validationError($e->errors());
        });

        $exceptions->render(function (DomainValidationException $e) {
            $errors = $e->getErrors();
            if (!empty($errors)) {
                return ApiResponse::validationError($errors);
            }
            return ApiResponse::error($e->getMessage(), 422);
        });

        $exceptions->render(function (BusinessConflictException $e) {
            return ApiResponse::error($e->getMessage(), 409);
        });

        $exceptions->render(function (NotFoundException $e) {
            return ApiResponse::error($e->getMessage(), 404);
        });

        $exceptions->render(function (QueryException $e) {
            return ApiResponse::error('A database error occurred.' . $e->getMessage(), 500);
        });

        $exceptions->render(function (\RuntimeException $e) {
            report($e);
            return ApiResponse::error('An internal error occurred.'. $e->getMessage(), 500);
        });

        $exceptions->render(function (\Throwable $e) {
            report($e);
            return ApiResponse::error('An unexpected error occurred.' . $e->getMessage(), 500);
        });
    })->create();
