<?php

namespace App\Support\SitePromotion;

class SitePromotionPackageInspection
{
    public function __construct(
        public readonly string $archiveDisk,
        public readonly string $archivePath,
        public readonly string $archiveName,
        public readonly array $manifest,
        public readonly array $payload,
        public readonly bool $includesAssets,
        public readonly array $warnings = [],
        public readonly array $errors = [],
    ) {}

    public function sourceSite(): array
    {
        return [
            'name' => $this->manifest['source_site_name'] ?? ($this->payload['site']['name'] ?? 'Unknown source site'),
            'handle' => $this->manifest['source_site_handle'] ?? ($this->payload['site']['handle'] ?? null),
            'domain' => $this->manifest['source_site_domain'] ?? ($this->payload['site']['domain'] ?? null),
            'package_type' => $this->manifest['package_type'] ?? null,
        ];
    }
}
