<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Format respons yang dipakai aplikasi Flutter (kasir/dapur/owner):
 * { success, message, data } dan { success: false, message, errors }.
 */
class ApiResponse
{
    public static function success(mixed $data = null, string $message = '', int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    /**
     * @param  array<string, array<int, string>>|null  $errors
     */
    public static function error(string $message, int $status = 400, ?array $errors = null): JsonResponse
    {
        return response()->json(array_filter([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
        ], fn ($value) => $value !== null), $status);
    }
}
