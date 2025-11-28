<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;

class ApiController extends BaseController
{
    protected function success(array|object|null $data = null, string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
            'code' => $status,
            'timestamp' => now()->toIso8601String(),
        ], $status);
    }

    protected function error(string $message = 'Error', int $status = 400, array|null $errors = null): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors,
            'code' => $status,
            'timestamp' => now()->toIso8601String(),
        ], $status);
    }
}
