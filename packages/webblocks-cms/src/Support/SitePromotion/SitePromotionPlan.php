<?php

namespace WebBlocks\Cms\Support\SitePromotion;

class SitePromotionPlan
{
    public function __construct(
        public readonly string $token,
        public readonly string $archiveDisk,
        public readonly string $archivePath,
        public readonly string $archiveName,
        public readonly array $sourceSite,
        public readonly array $targetSite,
        public readonly array $options,
        public readonly array $localeSummary,
        public readonly array $operations,
        public readonly array $preservedAreas,
        public readonly array $warnings,
        public readonly array $errors,
        public readonly array $summary,
        public readonly array $manifest = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            token: (string) ($data['token'] ?? ''),
            archiveDisk: (string) ($data['archive_disk'] ?? 'site-promotions'),
            archivePath: (string) ($data['archive_path'] ?? ''),
            archiveName: (string) ($data['archive_name'] ?? ''),
            sourceSite: (array) ($data['source_site'] ?? []),
            targetSite: (array) ($data['target_site'] ?? []),
            options: (array) ($data['options'] ?? []),
            localeSummary: (array) ($data['locale_summary'] ?? []),
            operations: (array) ($data['operations'] ?? []),
            preservedAreas: array_values((array) ($data['preserved_areas'] ?? [])),
            warnings: array_values((array) ($data['warnings'] ?? [])),
            errors: array_values((array) ($data['errors'] ?? [])),
            summary: (array) ($data['summary'] ?? []),
            manifest: (array) ($data['manifest'] ?? []),
        );
    }

    public function canApply(): bool
    {
        return $this->token !== '' && $this->archivePath !== '' && $this->errors === [];
    }

    public function strategy(): string
    {
        return (string) ($this->options['strategy'] ?? SitePromotionOptions::STRATEGY_ADDITIVE_UPDATE);
    }

    public function applyAssets(): bool
    {
        return (bool) ($this->options['apply_assets'] ?? false);
    }

    public function isMirror(): bool
    {
        return $this->strategy() === SitePromotionOptions::STRATEGY_MIRROR;
    }

    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'archive_disk' => $this->archiveDisk,
            'archive_path' => $this->archivePath,
            'archive_name' => $this->archiveName,
            'source_site' => $this->sourceSite,
            'target_site' => $this->targetSite,
            'options' => $this->options,
            'locale_summary' => $this->localeSummary,
            'operations' => $this->operations,
            'preserved_areas' => $this->preservedAreas,
            'warnings' => $this->warnings,
            'errors' => $this->errors,
            'summary' => $this->summary,
            'manifest' => $this->manifest,
        ];
    }
}
