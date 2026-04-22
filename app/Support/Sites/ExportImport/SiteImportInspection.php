<?php

namespace App\Support\Sites\ExportImport;

class SiteImportInspection
{
    public function __construct(
        public readonly array $manifest,
        public readonly bool $includesMedia,
    ) {}

    public function counts(): array
    {
        return (array) ($this->manifest['counts_summary'] ?? []);
    }

    public function sourceSiteName(): ?string
    {
        return $this->manifest['source_site_name'] ?? null;
    }

    public function sourceSiteHandle(): ?string
    {
        return $this->manifest['source_site_handle'] ?? null;
    }

    public function sourceSiteDomain(): ?string
    {
        return $this->manifest['source_site_domain'] ?? null;
    }
}
