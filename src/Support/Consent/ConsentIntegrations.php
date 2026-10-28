<?php

namespace WebBlocks\Cms\Support\Consent;

use InvalidArgumentException;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;

/** Trusted host/plugin providers register definitions; site overrides remain SQL-owned. */
final class ConsentIntegrations
{
  private array $definitions = [];

  public function register(string $handle, string $name, string $category, string $description, ?string $scriptUrl = null, ?string $privacyUrl = null, string $version = '1', ?string $plugin = null): void
  {
    if (! preg_match('/^[a-z][a-z0-9-]{0,39}$/D', $handle) || in_array($handle, ['cms-analytics', 'youtube', 'google-maps', 'vimeo', 'custom'], true)
      || ! in_array($category, ConsentPresets::CATEGORIES, true) || isset($this->definitions[$handle]) || count($this->definitions) >= 16
      || ($privacyUrl !== null && (! filter_var($privacyUrl, FILTER_VALIDATE_URL) || parse_url($privacyUrl, PHP_URL_SCHEME) !== 'https'))
      || ($scriptUrl !== null && (! filter_var($scriptUrl, FILTER_VALIDATE_URL) || parse_url($scriptUrl, PHP_URL_SCHEME) !== 'https'))) {
      throw new InvalidArgumentException('Invalid or duplicate consent integration.');
    }
    $this->definitions[$handle] = ['name' => $name, 'category' => $category, 'description' => $description, 'script_url' => $scriptUrl,
      'privacy_url' => $privacyUrl, 'plugin' => $plugin, 'revision' => substr(hash('sha256', $version.'|'.$category.'|'.$scriptUrl), 0, 36)];
  }

  public function all(): array
  {
    return array_filter($this->definitions, fn ($definition) => $definition['plugin'] === null || app(PluginRegistry::class)->isEnabled($definition['plugin']));
  }
}
