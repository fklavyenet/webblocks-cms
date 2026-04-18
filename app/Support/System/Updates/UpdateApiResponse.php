<?php

namespace App\Support\System\Updates;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\JsonResponse;

class UpdateApiResponse
{
    public static function success(array|Arrayable $data, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'api_version' => (string) config('webblocks-updates.api_version', '1'),
            'status' => 'ok',
            'data' => $data instanceof Arrayable ? $data->toArray() : $data,
            'meta' => array_merge([
                'generated_at' => now()->toIso8601String(),
            ], $meta),
        ], $status);
    }

    public static function error(string $code, string $message, int $status, array $meta = []): JsonResponse
    {
        return response()->json([
            'api_version' => (string) config('webblocks-updates.api_version', '1'),
            'status' => 'error',
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'meta' => array_merge([
                'generated_at' => now()->toIso8601String(),
            ], $meta),
        ], $status);
    }
}
