<?php

namespace WebBlocks\Cms\Support\Plugins;

use WebBlocks\Cms\Support\Plugins\Contracts\PluginPublicAssetExtension;

class PluginPublicAsset implements PluginPublicAssetExtension
{
  public const LOCATION_HEAD = 'head';

  public const LOCATION_BODY_END = 'body-end';

  public const TYPE_CSS = 'css';

  public const TYPE_JS = 'js';

  private string $pluginHandle = '';

  private bool $module = false;

  private bool $async = false;

  private function __construct(
    private readonly string $handle,
    private readonly string $type,
    private readonly string $url,
    private readonly string $location,
  ) {
    if (! PluginDashboardWidget::isValidKey($handle)) {
      throw PluginException::invalidAssetHandle($handle);
    }

    if (! in_array($type, [self::TYPE_CSS, self::TYPE_JS], true)) {
      throw new PluginException("Plugin asset [{$handle}] type must be css or js.");
    }

    if (trim($url) === '') {
      throw new PluginException("Plugin asset [{$handle}] URL cannot be empty.");
    }

    if (! in_array($location, [self::LOCATION_HEAD, self::LOCATION_BODY_END], true)) {
      throw new PluginException("Plugin asset [{$handle}] location must be head or body-end.");
    }
  }

  public static function cssHead(string $handle, string $url): self
  {
    return new self($handle, self::TYPE_CSS, $url, self::LOCATION_HEAD);
  }

  public static function jsHead(string $handle, string $url): self
  {
    return new self($handle, self::TYPE_JS, $url, self::LOCATION_HEAD);
  }

  public static function jsBodyEnd(string $handle, string $url): self
  {
    return new self($handle, self::TYPE_JS, $url, self::LOCATION_BODY_END);
  }

  public function forPlugin(string $pluginHandle): self
  {
    $this->pluginHandle = $pluginHandle;

    return $this;
  }

  public function pluginHandle(): string
  {
    return $this->pluginHandle;
  }

  public function handle(): string
  {
    return $this->handle;
  }

  public function type(): string
  {
    return $this->type;
  }

  public function url(): string
  {
    return $this->url;
  }

  public function location(): string
  {
    return $this->location;
  }

  public function module(bool $module = true): self
  {
    $this->module = $module;

    return $this;
  }

  public function isModule(): bool
  {
    return $this->module;
  }

  public function async(bool $async = true): self
  {
    $this->async = $async;

    return $this;
  }

  public function isAsync(): bool
  {
    return $this->async;
  }
}
