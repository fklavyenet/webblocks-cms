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
}
