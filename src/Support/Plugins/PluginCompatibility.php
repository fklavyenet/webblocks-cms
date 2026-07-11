<?php

namespace WebBlocks\Cms\Support\Plugins;

use WebBlocks\Cms\Support\WebBlocks;

class PluginCompatibility
{
  public function cmsVersion(): string
  {
    return WebBlocks::version();
  }

  public function isCompatible(PluginDefinition $plugin): bool
  {
    $constraint = $plugin->requiredCmsVersion();

    if ($constraint === null) {
      return true;
    }

    return $this->matchesConstraint($this->cmsVersion(), $constraint);
  }

  public function incompatibilityMessage(PluginDefinition $plugin): ?string
  {
    if ($this->isCompatible($plugin)) {
      return null;
    }

    return 'Requires WebBlocks CMS '.$plugin->requiredCmsVersion().'; installed CMS is '.$this->cmsVersion().'.';
  }

  private function matchesConstraint(string $version, string $constraint): bool
  {
    foreach (preg_split('/\s+/', trim($constraint)) ?: [] as $part) {
      if ($part === '') {
        continue;
      }

      if (! $this->matchesConstraintPart($version, $part)) {
        return false;
      }
    }

    return true;
  }

  private function matchesConstraintPart(string $version, string $part): bool
  {
    if (preg_match('/^\^(\d+)(?:\.(\d+))?(?:\.(\d+))?$/', $part, $matches) === 1) {
      $major = (int) $matches[1];
      $minor = (int) ($matches[2] ?? 0);
      $patch = (int) ($matches[3] ?? 0);
      $minimum = "{$major}.{$minor}.{$patch}";
      $maximum = ($major + 1).'.0.0';

      return version_compare($version, $minimum, '>=') && version_compare($version, $maximum, '<');
    }

    if (preg_match('/^(>=|<=|>|<|=)?(\d+\.\d+\.\d+)$/', $part, $matches) === 1) {
      return version_compare($version, $matches[2], $matches[1] ?: '=');
    }

    throw new PluginException("Plugin CMS version constraint [{$part}] is not supported.");
  }
}
