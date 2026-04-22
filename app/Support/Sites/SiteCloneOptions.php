<?php

namespace App\Support\Sites;

class SiteCloneOptions
{
    public function __construct(
        public readonly ?string $targetName = null,
        public readonly ?string $targetHandle = null,
        public readonly ?string $targetDomain = null,
        public readonly bool $withNavigation = true,
        public readonly bool $withMedia = true,
        public readonly bool $copyMediaFiles = false,
        public readonly bool $withTranslations = true,
        public readonly bool $overwriteTarget = false,
        public readonly bool $dryRun = false,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            targetName: self::stringOrNull($data['target_name'] ?? null),
            targetHandle: self::stringOrNull($data['target_handle'] ?? null),
            targetDomain: self::stringOrNull($data['target_domain'] ?? null),
            withNavigation: (bool) ($data['with_navigation'] ?? true),
            withMedia: (bool) ($data['with_media'] ?? true),
            copyMediaFiles: (bool) ($data['copy_media_files'] ?? false),
            withTranslations: (bool) ($data['with_translations'] ?? true),
            overwriteTarget: (bool) ($data['overwrite_target'] ?? false),
            dryRun: (bool) ($data['dry_run'] ?? false),
        );
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
