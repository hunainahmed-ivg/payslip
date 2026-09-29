<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiResponse
{
    public static function ok(mixed $data = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => $data,
            'error' => null,
        ], $status);
    }

    public static function error(string $error, int $status = 422, mixed $data = null): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'data' => $data,
            'error' => $error,
        ], $status);
    }
}
