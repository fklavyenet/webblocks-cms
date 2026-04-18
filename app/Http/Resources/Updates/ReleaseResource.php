<?php

namespace App\Http\Resources\Updates;

use App\Models\SystemRelease;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin SystemRelease */
class ReleaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'version' => $this->version,
            'name' => $this->release_name,
            'description' => $this->description,
            'changelog' => $this->changelog,
            'download_url' => $this->download_url,
            'checksum_sha256' => $this->checksum_sha256,
            'published_at' => $this->published_at?->toIso8601String(),
            'is_critical' => (bool) $this->is_critical,
            'is_security' => (bool) $this->is_security,
            'requirements' => [
                'min_php_version' => $this->min_php_version,
                'min_laravel_version' => $this->min_laravel_version,
                'supported_from_version' => $this->supported_from_version,
                'supported_until_version' => $this->supported_until_version,
            ],
            'metadata' => $this->metadata,
        ];
    }
}
