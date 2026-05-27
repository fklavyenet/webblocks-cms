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
}
