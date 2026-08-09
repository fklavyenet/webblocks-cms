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

  /**
   * Whether a version satisfies a constraint, without throwing.
   *
   * The CMS-version path below treats an unsupported constraint as a fault worth
   * stopping for, because that constraint is the CMS's own contract. A plugin's
   * `requires` entries are third-party text about third-party software, so an
   * unreadable one there is reported rather than fatal — a malformed manifest must
   * not be able to take an admin screen down.
   */
  public function satisfies(string $version, string $constraint): bool
  {
    foreach (preg_split('/\s+/', trim($constraint)) ?: [] as $part) {
      if ($part === '') {
        continue;
      }

      if ($this->comparePart($version, $part) !== true) {
        return false;
      }
    }

    return true;
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
    $result = $this->comparePart($version, $part);

    if ($result === null) {
      throw new PluginException("Plugin CMS version constraint [{$part}] is not supported.");
    }

    return $result;
  }

  /**
   * Compare one constraint part, or null when it cannot be read.
   *
   * Versions are normalised to three segments first, because a real manifest writes
   * `>=8.3` for PHP and `1.46` for a plugin far more often than it writes a strict
   * three-part semver, and refusing those would make the feature unusable on the
   * manifests that already exist.
   */
  private function comparePart(string $version, string $part): ?bool
  {
    $version = $this->normalize($version);

    if (preg_match('/^\^(\d+)(?:\.(\d+))?(?:\.(\d+))?$/', $part, $matches) === 1) {
      $major = (int) $matches[1];
      $minor = (int) ($matches[2] ?? 0);
      $patch = (int) ($matches[3] ?? 0);
      $minimum = "{$major}.{$minor}.{$patch}";
      $maximum = ($major + 1).'.0.0';

      return version_compare($version, $minimum, '>=') && version_compare($version, $maximum, '<');
    }

    if (preg_match('/^(>=|<=|>|<|=)?(\d+(?:\.\d+){0,2})$/', $part, $matches) === 1) {
      return version_compare($version, $this->normalize($matches[2]), $matches[1] ?: '=');
    }

    return null;
  }

  /**
   * `8.3` becomes `8.3.0`, so it compares as a version rather than as a string.
   */
  private function normalize(string $version): string
  {
    $version = trim($version);

    if (preg_match('/^\d+(\.\d+){0,2}$/', $version) !== 1) {
      return $version;
    }

    $parts = array_pad(explode('.', $version), 3, '0');

    return implode('.', array_slice($parts, 0, 3));
  }
}
