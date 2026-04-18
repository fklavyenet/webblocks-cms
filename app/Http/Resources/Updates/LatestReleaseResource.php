<?php

namespace App\Http\Resources\Updates;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LatestReleaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'product' => $this['product'],
            'channel' => $this['channel'],
            'installed_version' => $this['installed_version'],
            'latest_version' => $this['latest_version'],
            'update_available' => $this['update_available'],
            'compatibility' => [
                'status' => $this['compatibility']['status'],
                'reasons' => $this['compatibility']['reasons'],
            ],
            'release' => ReleaseResource::make($this['release'])->resolve(),
        ];
    }
}
