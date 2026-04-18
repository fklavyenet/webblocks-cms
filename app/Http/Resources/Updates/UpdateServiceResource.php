<?php

namespace App\Http\Resources\Updates;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UpdateServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $baseUrl = rtrim((string) config('webblocks-updates.server.base_url', url('/')), '/');

        return [
            'service' => config('webblocks-updates.server.service_name', 'WebBlocks Update Server'),
            'default_channel' => config('webblocks-updates.server.default_channel', 'stable'),
            'products' => $this['products'],
            'supported_api_versions' => [(string) config('webblocks-updates.api_version', '1')],
            'server_time' => now()->toIso8601String(),
            'endpoints' => [
                'latest' => $baseUrl.'/api/updates/{product}/latest',
                'release' => $baseUrl.'/api/updates/{product}/releases/{version}',
                'releases' => $baseUrl.'/api/updates/{product}/releases',
            ],
        ];
    }
}
