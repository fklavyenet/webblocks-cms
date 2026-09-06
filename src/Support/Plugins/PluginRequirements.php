<?php

namespace WebBlocks\Cms\Support\Plugins;

use Composer\InstalledVersions;
use Throwable;

/**
 * What a plugin says it needs, checked against what this install has.
 *
 * The manifest's `requires` key has been documented and written by every catalog
 * plugin since the beginning, and read by nothing. A plugin depending on another one
 * therefore degraded in silence: the operator who disabled the dependency saw a
 * feature stop working with nothing anywhere to say why.
 *
 * This reports; it never blocks. Refusing to enable a plugin until its dependencies
 * are enabled needs a resolver the CMS does not have, in an install model where
 * plugins arrive as manually uploaded ZIPs — and it produces the deadlock where A
 * cannot be enabled because B is disabled and B cannot be disabled because A is
 * enabled. A warning an operator can read is worth more than a rule that traps them.
 *
 * Every cross-plugin dependency is therefore expected to stay soft at runtime: the
 * depending plugin must keep working with the dependency absent, and say so itself.
 */
class PluginRequirements
{
  /**
   * The requirement key that means the CMS itself.
   *
   * `required_cms_version` is the older and authoritative declaration, so this one is
   * checked only to catch a manifest whose two statements disagree — a real mistake
   * that is otherwise invisible.
   */
  public const CMS = 'webblocks-cms';

  public const PHP = 'php';

  public function __construct(
    private readonly PluginCompatibility $compatibility = new PluginCompatibility,
  ) {}

  /**
   * Unmet requirements, as sentences for an operator.
   *
   * An empty array means everything declared is satisfied — including the case of a
   * plugin that declares nothing, which is most of them.
   *
   * @param  array<string, PluginDefinition>  $installed  keyed by handle
   * @param  callable(string): bool  $isEnabled
   * @return list<string>
   */
  public function unmet(PluginDefinition $plugin, array $installed, callable $isEnabled): array
  {
    $problems = [];

    foreach ($plugin->requirements() as $handle => $constraint) {
      $handle = (string) $handle;
      $constraint = trim((string) $constraint);

      if ($handle === '' || $constraint === '') {
        continue;
      }

      $problem = match (true) {
        $handle === self::CMS => $this->checkCms($plugin, $constraint),
        $handle === self::PHP => $this->checkPhp($constraint),
        str_contains($handle, '/') => $this->checkComposerPackage($handle, $constraint),
        default => $this->checkPlugin($handle, $constraint, $installed, $isEnabled),
      };

      if ($problem !== null) {
        $problems[] = $problem;
      }
    }

    return $problems;
  }

  private function checkCms(PluginDefinition $plugin, string $constraint): ?string
  {
    $version = $this->compatibility->cmsVersion();

    if ($this->compatibility->satisfies($version, $constraint)) {
      return null;
    }

    /*
     * Worth distinguishing. If `required_cms_version` is satisfied but this is not,
     * the manifest contradicts itself, and saying "requires X" when the other field
     * says Y would send the operator looking for a CMS release that would not help.
     */
    if ($plugin->requiredCmsVersion() !== null
      && $this->compatibility->satisfies($version, (string) $plugin->requiredCmsVersion())) {
      return 'The manifest disagrees with itself about the CMS version: requires '.$constraint
        .' but declares required_cms_version '.$plugin->requiredCmsVersion().'.';
    }

    return 'Requires WebBlocks CMS '.$constraint.'; installed is '.$version.'.';
  }

  private function checkPhp(string $constraint): ?string
  {
    $version = PHP_VERSION;

    if ($this->compatibility->satisfies($version, $constraint)) {
      return null;
    }

    return 'Requires PHP '.$constraint.'; running '.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION.'.'.PHP_RELEASE_VERSION.'.';
  }

  private function checkComposerPackage(string $package, string $constraint): ?string
  {
    try {
      if (! InstalledVersions::isInstalled($package)) {
        return 'Requires the Composer package '.$package.' '.$constraint.', which is not installed.';
      }

      $version = InstalledVersions::getVersion($package)
        ?? InstalledVersions::getPrettyVersion($package);
    } catch (Throwable) {
      return 'Requires the Composer package '.$package.' '.$constraint.', whose installed version could not be read.';
    }

    if (! is_string($version) || $version === '' || ! $this->compatibility->satisfies($version, $constraint)) {
      return 'Requires the Composer package '.$package.' '.$constraint.'; installed is '
        .(is_string($version) && $version !== '' ? $version : 'unknown').'.';
    }

    return null;
  }

  /**
   * @param  array<string, PluginDefinition>  $installed
   * @param  callable(string): bool  $isEnabled
   */
  private function checkPlugin(string $handle, string $constraint, array $installed, callable $isEnabled): ?string
  {
    $dependency = $installed[$handle] ?? null;

    if ($dependency === null) {
      return 'Requires the plugin '.$handle.' '.$constraint.', which is not installed.';
    }

    /*
     * Enabled is checked as well as installed, and separately, because they are
     * different things to fix. A disabled plugin's classes are never loaded, so a
     * dependency that is present but off is exactly as absent as one that was never
     * uploaded — and the operator's next action is different in each case.
     */
    if (! $isEnabled($handle)) {
      return 'Requires the plugin '.$handle.', which is installed but not enabled.';
    }

    $version = (string) $dependency->versionText();

    if ($version === '' || ! $this->compatibility->satisfies($version, $constraint)) {
      return 'Requires '.$handle.' '.$constraint.'; installed is '.($version !== '' ? $version : 'unknown').'.';
    }

    return null;
  }
}
