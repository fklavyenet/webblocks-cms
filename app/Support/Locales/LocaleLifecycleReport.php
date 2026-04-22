<?php

namespace App\Support\Locales;

use App\Models\Locale;

class LocaleLifecycleReport
{
    public function __construct(
        public readonly Locale $locale,
        private readonly array $counts,
    ) {}

    public function count(string $key): int
    {
        return (int) ($this->counts[$key] ?? 0);
    }

    public function inUse(): bool
    {
        return $this->count('site_assignments') > 0
            || $this->count('page_translations') > 0
            || $this->count('block_translation_rows') > 0;
    }

    public function canEnable(): bool
    {
        return ! $this->locale->is_default && ! $this->locale->is_enabled;
    }

    public function canDisable(): bool
    {
        return ! $this->locale->is_default && $this->locale->is_enabled;
    }

    public function canDelete(): bool
    {
        return ! $this->locale->is_default
            && ! $this->locale->is_enabled
            && ! $this->inUse();
    }

    public function disableBlockedReason(): ?string
    {
        if ($this->locale->is_default) {
            return 'Default locale cannot be disabled.';
        }

        if (! $this->locale->is_enabled) {
            return 'Locale is already disabled.';
        }

        return null;
    }

    public function deleteBlockedReason(): ?string
    {
        if ($this->locale->is_default) {
            return 'Default locale cannot be deleted.';
        }

        if ($this->locale->is_enabled) {
            return 'Disable locale before deleting it.';
        }

        if ($this->inUse()) {
            return 'Cannot delete because this locale is in use.';
        }

        return null;
    }

    public function usageSummary(): array
    {
        return array_filter([
            'site assignments' => $this->count('site_assignments'),
            'page translations' => $this->count('page_translations'),
            'block translation rows' => $this->count('block_translation_rows'),
        ]);
    }
}
