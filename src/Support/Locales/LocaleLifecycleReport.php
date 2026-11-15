<?php

namespace WebBlocks\Cms\Support\Locales;

use WebBlocks\Cms\Models\Locale;

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
      return __('webblocks-cms::admin-feedback.locales.default_disable');
    }

    if (! $this->locale->is_enabled) {
      return __('webblocks-cms::admin-feedback.locales.already_disabled');
    }

    return null;
  }

  public function deleteBlockedReason(): ?string
  {
    if ($this->locale->is_default) {
      return __('webblocks-cms::admin-feedback.locales.default_delete');
    }

    if ($this->locale->is_enabled) {
      return __('webblocks-cms::admin-feedback.locales.disable_before_delete');
    }

    if ($this->inUse()) {
      return __('webblocks-cms::admin-feedback.locales.delete_in_use');
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
