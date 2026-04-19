<?php

namespace App\Support\Release;

use Illuminate\Support\Arr;

class ReleaseMetadataFactory
{
    public function make(array $build, array $overrides = []): array
    {
        return array_filter([
            'product' => Arr::get($build, 'product'),
            'version' => Arr::get($build, 'version'),
            'channel' => Arr::get($build, 'channel'),
            'notes' => Arr::get($build, 'notes'),
            'checksum_sha256' => Arr::get($build, 'checksum_sha256'),
            'package_filename' => Arr::get($build, 'filename'),
            'package_size' => Arr::get($build, 'size'),
            'built_at' => Arr::get($build, 'built_at'),
            'source_commit' => Arr::get($build, 'source_commit'),
            'source_tag' => Arr::get($build, 'source_tag'),
            'minimum_supported_version' => Arr::get($build, 'minimum_supported_version'),
            'package_url' => Arr::get($build, 'package_url'),
            'included_root_items' => Arr::get($build, 'included_root_items'),
        ], fn ($value): bool => $value !== null);
    }
}
