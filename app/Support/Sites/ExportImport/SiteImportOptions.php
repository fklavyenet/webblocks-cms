<?php

namespace App\Support\Sites\ExportImport;

class SiteImportOptions
{
    public function __construct(
        public readonly string $siteName,
        public readonly ?string $siteHandle,
        public readonly ?string $siteDomain,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            siteName: trim((string) ($data['site_name'] ?? '')),
            siteHandle: ($handle = trim((string) ($data['site_handle'] ?? ''))) !== '' ? $handle : null,
            siteDomain: ($domain = trim((string) ($data['site_domain'] ?? ''))) !== '' ? $domain : null,
        );
    }
}
