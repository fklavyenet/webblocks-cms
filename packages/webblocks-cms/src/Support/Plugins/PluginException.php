<?php

namespace WebBlocks\Cms\Support\Plugins;

use InvalidArgumentException;

class PluginException extends InvalidArgumentException
{
  public static function invalidHandle(string $handle): self
  {
    return new self("Plugin handle [{$handle}] must be kebab-case.");
  }

  public static function invalidLabel(string $handle): self
  {
    return new self("Plugin [{$handle}] must define a label.");
  }

  public static function invalidVersion(string $handle, string $version): self
  {
    return new self("Plugin [{$handle}] version [{$version}] must be semver-like.");
  }

  public static function duplicateHandle(string $handle): self
  {
    return new self("Plugin handle [{$handle}] is already registered.");
  }

  public static function duplicateMenuItem(string $handle, string $key): self
  {
    return new self("Plugin [{$handle}] already defines menu item [{$key}].");
  }

  public static function invalidPermissionPrefix(string $handle, string $permission): self
  {
    return new self("Plugin permission [{$permission}] must start with [{$handle}.].");
  }

  public static function invalidExtensionKey(string $key): self
  {
    return new self("Plugin extension key [{$key}] must be dot-namespaced kebab-case.");
  }

  public static function invalidExtensionOwnership(string $handle, string $key): self
  {
    return new self("Plugin extension key [{$key}] must start with [{$handle}.].");
  }

  public static function duplicateExtensionKey(string $key): self
  {
    return new self("Plugin extension key [{$key}] is already registered.");
  }

  public static function invalidBlockHandle(string $handle): self
  {
    return new self("Plugin block handle [{$handle}] must use plugin-handle::block-handle namespacing.");
  }

  public static function invalidBlockOwnership(string $pluginHandle, string $blockHandle): self
  {
    return new self("Plugin block handle [{$blockHandle}] must start with [{$pluginHandle}::].");
  }

  public static function duplicateBlockHandle(string $handle): self
  {
    return new self("Plugin block handle [{$handle}] is already registered.");
  }

  public static function invalidAssetHandle(string $handle): self
  {
    return new self("Plugin asset handle [{$handle}] must be dot-namespaced kebab-case.");
  }

  public static function duplicateAssetHandle(string $handle): self
  {
    return new self("Plugin asset handle [{$handle}] is already registered.");
  }

  public static function invalidNamespace(string $namespace): self
  {
    return new self("Plugin-owned namespace [{$namespace}] must be kebab-case.");
  }
}
