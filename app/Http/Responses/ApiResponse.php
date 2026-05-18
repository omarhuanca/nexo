<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    /**
     * Respuesta exitosa estándar
     *
     * @param string $message
     * @param int $statusCode
     * @param mixed $data
     * @return JsonResponse
     */
    public static function success(string $message = 'Success', int $statusCode = 200, mixed $data = []): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    /**
     * Respuesta de error estándar
     *
     * @param string $message
     * @param int $statusCode
     * @param mixed $data
     * @return JsonResponse
     */
    public static function error(string $message = 'Server Error', int $statusCode = 500, mixed $data = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data
        ], $statusCode);
    }

    /**
     * Respuesta de validación fallida
     *
     * @param array $errors
     * @return JsonResponse
     */
    public static function validationError(array $errors): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Validation Error',
            'errors' => $errors
        ], 422);
    }

    /**
     * Respuesta de recurso no encontrado
     *
     * @param string $resource
     * @return JsonResponse
     */
    public static function notFound(string $resource = 'Resource'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => "{$resource} not found"
        ], 404);
    }

    /**
     * Respuesta de recurso creado
     *
     * @param string $message
     * @param mixed $data
     * @return JsonResponse
     */
    public static function created(string $message = 'Resource created successfully', mixed $data = []): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data
        ], 201);
    }
}