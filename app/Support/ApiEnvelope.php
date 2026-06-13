<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

class ApiEnvelope
{
    public static function ok($data = [], string $message = '成功', int $status = 200): JsonResponse
    {
        return response()->json([
            'code' => 0,
            'message' => $message,
            'data' => $data,
            'timestamp' => self::timestamp(),
        ], $status)->header('Cache-Control', 'no-store');
    }

    public static function error(string $message, int $status = 400, ?int $code = null, $data = null): JsonResponse
    {
        return response()->json([
            'code' => $code ?? $status,
            'message' => $message,
            'data' => $data,
            'timestamp' => self::timestamp(),
        ], $status)->header('Cache-Control', 'no-store');
    }

    public static function timestamp(): int
    {
        return (int) floor(microtime(true) * 1000);
    }
}
