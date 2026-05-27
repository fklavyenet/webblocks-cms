<?php

namespace WebBlocks\Cms\Plugins\WebBlocksUiManager\Support;

class WebBlocksUiManagerPaths
{
  public function defaultCdnBasePath(): string
  {
    return trim((string) config('webblocks-plugins.webblocks_ui_manager.cdn_base_path', 'cdn/webblocks-ui'), '/');
  }

  public function defaultCdnBaseUrl(): ?string
  {
    $url = trim((string) config('webblocks-plugins.webblocks_ui_manager.cdn_base_url', ''));

    return $url !== '' ? rtrim($url, '/') : null;
  }

  public function releasePublicDirectory(string $version): string
  {
    return $this->defaultCdnBasePath().'/'.$version;
  }

  public function cdnRootPublicPath(): string
  {
    return public_path($this->defaultCdnBasePath());
  }

  public function releasePublicPath(string $version): string
  {
    return public_path($this->releasePublicDirectory($version));
  }

  public function manifestPublicPath(string $version): string
  {
    return $this->releasePublicPath($version).'/manifest.json';
  }

  public function manifestRelativePath(string $version): string
  {
    return $this->releasePublicDirectory($version).'/manifest.json';
  }

  /**
   * @return array<int, string>
   */
  public function expectedDistFiles(): array
  {
    $files = config('webblocks-plugins.webblocks_ui_manager.expected_dist_files', [
      'webblocks-ui.css',
      'webblocks-icons.css',
      'webblocks-ui.js',
    ]);

    if (! is_array($files)) {
      return [];
    }

    return array_values(array_filter(
      array_map(fn ($file): string => basename((string) $file), $files),
      fn (string $file): bool => $file !== ''
    ));
  }
}
