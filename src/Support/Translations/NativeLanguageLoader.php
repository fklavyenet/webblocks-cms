<?php

namespace WebBlocks\Cms\Support\Translations;

use Illuminate\Contracts\Translation\Loader;

final class NativeLanguageLoader implements Loader
{
  public function __construct(private readonly Loader $loader) {}

  public function load($locale, $group, $namespace = null): array
  {
    $file = $this->loader->load($locale, $group, $namespace);
    if (! in_array($namespace, ['webblocks-cms', 'publisher-client'], true)
      || in_array($locale, AdminLocaleResolver::SUPPORTED_LOCALES, true)) {
      return $file;
    }

    $installed = app(NativeLanguagePacks::class)->lines($locale, $namespace.'::'.$group);

    return array_replace_recursive($installed, $file);
  }

  public function addNamespace($namespace, $hint): void
  {
    $this->loader->addNamespace($namespace, $hint);
  }

  public function addJsonPath($path): void
  {
    $this->loader->addJsonPath($path);
  }

  public function addPath($path): void
  {
    $this->loader->addPath($path);
  }

  public function namespaces(): array
  {
    return $this->loader->namespaces();
  }
}
