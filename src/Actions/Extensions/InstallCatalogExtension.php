<?php

namespace WebBlocks\Cms\Actions\Extensions;

use WebBlocks\Cms\Support\Extensions\ExtensionOperation;
use WebBlocks\Cms\Support\Theme\PublicThemes;
use WebBlocks\Cms\Support\Theme\ThemeCatalog;
use WebBlocks\Cms\Support\Translations\LanguagePackCatalog;
use WebBlocks\Cms\Support\Translations\NativeLanguagePacks;
use WebBlocks\Cms\Support\WebBlocks;

final class InstallCatalogExtension
{
  public function __construct(
    private readonly ThemeCatalog $themes,
    private readonly PublicThemes $installedThemes,
    private readonly LanguagePackCatalog $languages,
    private readonly NativeLanguagePacks $installedLanguages,
  ) {}

  public function theme(string $handle, string $version, string $checksum, bool $update, ExtensionOperation $operation): array
  {
    abort_unless($this->themes->enabled() && $this->installedThemes->ready(), 503);
    $versions = $this->installedThemes->installedCatalogChecksums([$handle])[$handle] ?? [];
    if (($versions !== []) !== $update || ($update && collect(array_keys($versions))->contains(fn (string $current) => version_compare($version, $current, '<=')))) {
      throw new CatalogInstallStateConflict;
    }
    $this->themes->install($handle, $version, $operation, $checksum);

    return ['handle' => $handle, 'version' => $version, 'sha256' => $checksum, 'activation_changed' => false];
  }

  public function language(string $code, string $version, string $checksum, bool $update, ExtensionOperation $operation): array
  {
    abort_unless($this->languages->enabled(), 503);
    $current = $this->installedLanguages->installed()->get($code);
    if (($current !== null) !== $update || ($update && $current->source_version === WebBlocks::VERSION
      && $current->release_version !== '' && version_compare($version, $current->release_version, '<='))) {
      throw new CatalogInstallStateConflict;
    }
    $this->languages->install($code, $version, $checksum, $operation);

    return ['code' => $code, 'version' => $version, 'sha256' => $checksum, 'default_language_changed' => false];
  }
}
