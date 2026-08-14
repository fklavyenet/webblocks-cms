<?php

namespace WebBlocks\Cms\Tests\Feature;

use WebBlocks\Cms\Support\Plugins\PluginCompatibility;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Plugins\PluginRequirements;
use WebBlocks\Cms\Tests\TestCase;

/**
 * The manifest's `requires` key, which was documented and written by every catalog
 * plugin from the beginning and read by nothing.
 *
 * The consequence was silent: a plugin depending on another one degraded with no
 * explanation anywhere, so the operator who disabled the dependency saw a feature
 * stop and had nothing to read. These cover both halves of the fix — that unmet
 * requirements are reported accurately, and that they are only ever reported.
 */
class PluginRequirementsTest extends TestCase
{
  public function test_a_plugin_that_declares_nothing_has_nothing_unmet(): void
  {
    // Most plugins. The check must be silent for them rather than inventing advice.
    $this->assertSame([], $this->unmet($this->plugin()));
  }

  public function test_a_missing_dependency_is_reported(): void
  {
    $problems = $this->unmet($this->plugin(['webblocks-campaigns' => '^0.7']));

    $this->assertCount(1, $problems);
    $this->assertStringContainsString('webblocks-campaigns', $problems[0]);
    $this->assertStringContainsString('not installed', $problems[0]);
  }

  public function test_an_installed_but_disabled_dependency_is_reported_differently(): void
  {
    /*
     * Installed-but-off and never-installed need different sentences because they
     * need different actions, and a disabled plugin's classes are never loaded — so
     * to the depending plugin it is exactly as absent as one that was never uploaded.
     */
    $problems = $this->unmet(
      $this->plugin(['webblocks-campaigns' => '^0.7']),
      ['webblocks-campaigns' => $this->plugin([], 'webblocks-campaigns', '0.7.0')],
      enabled: [],
    );

    $this->assertCount(1, $problems);
    $this->assertStringContainsString('not enabled', $problems[0]);
  }

  public function test_a_satisfied_dependency_reports_nothing(): void
  {
    $problems = $this->unmet(
      $this->plugin(['webblocks-campaigns' => '^0.7']),
      ['webblocks-campaigns' => $this->plugin([], 'webblocks-campaigns', '0.7.3')],
      enabled: ['webblocks-campaigns'],
    );

    $this->assertSame([], $problems);
  }

  public function test_a_dependency_below_the_constraint_is_reported(): void
  {
    $problems = $this->unmet(
      $this->plugin(['webblocks-campaigns' => '^0.7']),
      ['webblocks-campaigns' => $this->plugin([], 'webblocks-campaigns', '0.6.9')],
      enabled: ['webblocks-campaigns'],
    );

    $this->assertCount(1, $problems);
    $this->assertStringContainsString('0.6.9', $problems[0]);
  }

  public function test_a_two_segment_constraint_is_understood(): void
  {
    /*
     * Real manifests write `>=8.3` for PHP and `1.46` for a plugin far more often
     * than a strict three-part semver. Refusing those would make the feature
     * unusable on the manifests that already exist.
     */
    $compatibility = new PluginCompatibility;

    $this->assertTrue($compatibility->satisfies('8.3.11', '>=8.3'));
    $this->assertFalse($compatibility->satisfies('8.2.0', '>=8.3'));
    $this->assertTrue($compatibility->satisfies('1.46.7', '^1.45'));
  }

  public function test_an_unreadable_constraint_is_reported_rather_than_thrown(): void
  {
    /*
     * A manifest is third-party text. An unparsable constraint in it must not be
     * able to take the plugin screen down — unlike the CMS's own constraint, which
     * is the CMS's contract and still throws.
     */
    $problems = $this->unmet(
      $this->plugin(['webblocks-campaigns' => 'whenever~ish']),
      ['webblocks-campaigns' => $this->plugin([], 'webblocks-campaigns', '0.7.0')],
      enabled: ['webblocks-campaigns'],
    );

    $this->assertCount(1, $problems);
  }

  public function test_php_is_checked_against_the_running_version(): void
  {
    $this->assertSame([], $this->unmet($this->plugin(['php' => '>=8.0'])));

    $problems = $this->unmet($this->plugin(['php' => '>=99.0']));
    $this->assertCount(1, $problems);
    $this->assertStringContainsString('PHP', $problems[0]);
  }

  public function test_a_manifest_that_contradicts_itself_about_the_cms_says_so(): void
  {
    /*
     * `required_cms_version` is the authoritative declaration. When it is satisfied
     * and `requires.webblocks-cms` is not, the manifest disagrees with itself — and
     * repeating "requires X" would send an operator looking for a CMS release that
     * would not help them.
     */
    $plugin = $this->plugin(['webblocks-cms' => '^99.0'])->requiresCms('^1.0');

    $problems = $this->unmet($plugin);

    $this->assertCount(1, $problems);
    $this->assertStringContainsString('disagrees with itself', $problems[0]);
  }

  public function test_requirements_are_read_from_the_manifest_shape(): void
  {
    $plugin = $this->plugin()->requires([
      'webblocks-cms' => '^1.45.6',
      'php' => '>=8.3',
      'ignored-because-blank' => '  ',
      'ignored-because-not-scalar' => ['nope'],
    ]);

    $this->assertSame(
      ['webblocks-cms' => '^1.45.6', 'php' => '>=8.3'],
      $plugin->requirements(),
    );
  }

  public function test_requirements_appear_in_the_definition_payload(): void
  {
    // The admin screen reads this array; without the key it would render nothing and
    // no test would notice.
    $payload = $this->plugin(['php' => '>=8.3'])->toArray(true);

    $this->assertSame(['php' => '>=8.3'], $payload['requires']);
  }

  public function test_the_registry_can_report_requirements_for_a_registered_plugin(): void
  {
    /*
     * The admin plugin screens obtain this value through PluginRegistry rather
     * than calling PluginRequirements directly. Keep that integration covered:
     * a stale call to a nonexistent registry lookup method took every plugin
     * screen down before it could render.
     */
    $registry = new PluginRegistry;
    $registry->register($this->plugin(['webblocks-campaigns' => '^0.7']));

    $problems = $registry->unmetRequirements('webblocks-forms');

    $this->assertCount(1, $problems);
    $this->assertStringContainsString('webblocks-campaigns', $problems[0]);
    $this->assertSame([], $registry->unmetRequirements('not-installed'));
  }

  /**
   * @param  array<string, string>  $requires
   */
  private function plugin(array $requires = [], string $handle = 'webblocks-forms', string $version = '0.4.0'): PluginDefinition
  {
    return PluginDefinition::make($handle)
      ->label('Test Plugin')
      ->version($version)
      ->requires($requires);
  }

  /**
   * @param  array<string, PluginDefinition>  $installed
   * @param  list<string>  $enabled
   * @return list<string>
   */
  private function unmet(PluginDefinition $plugin, array $installed = [], array $enabled = []): array
  {
    return (new PluginRequirements)->unmet(
      $plugin,
      $installed,
      fn (string $handle): bool => in_array($handle, $enabled, true),
    );
  }
}
