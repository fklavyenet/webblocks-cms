<?php

namespace WebBlocks\Cms\Tests\Feature;

use RuntimeException;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginDependencies;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Tests\TestCase;

class PluginDependencyTest extends TestCase
{
  private function plugin(string $handle, array $dependencies = [], string $version = '1.0.0'): PluginDefinition
  {
    return PluginDefinition::make($handle)->label($handle)->version($version)->dependencies($dependencies);
  }

  public function test_optional_provider_does_not_disable_guest_booking(): void
  {
    $registry = new PluginRegistry(['appointments' => true]);
    $registry->register($this->plugin('appointments', ['optional' => ['memberships' => '^1.0']]));
    $this->assertTrue($registry->isActive('appointments'));
    $this->assertCount(1, $registry->summaries()[0]['optional_dependency_problems']);
  }

  public function test_required_dependencies_are_checked_transitively_and_after_suppression(): void
  {
    $registry = new PluginRegistry(['portal' => true, 'memberships' => true, 'accounts' => true]);
    $registry->register($this->plugin('portal', ['required' => ['memberships' => '^1.0']]));
    $registry->register($this->plugin('memberships', ['required' => ['accounts' => '^1.0']]));
    $registry->register($this->plugin('accounts'));
    $this->assertTrue($registry->isActive('portal'));
    $registry->suppress('accounts');
    $this->assertFalse($registry->isActive('portal'));
    $this->assertFalse($registry->isActive('memberships'));
  }

  public function test_incompatible_version_disables_required_consumer_only(): void
  {
    $registry = new PluginRegistry(['portal' => true, 'memberships' => true]);
    $registry->register($this->plugin('portal', ['required' => ['memberships' => '^1.0']]));
    $registry->register($this->plugin('memberships', version: '2.0.0'));
    $this->assertFalse($registry->isActive('portal'));
    $this->assertTrue($registry->isActive('memberships'));
  }

  public function test_cycles_fail_closed_without_recursive_boot(): void
  {
    $registry = new PluginRegistry(['one' => true, 'two' => true]);
    $registry->register($this->plugin('one', ['required' => ['two' => '*']]));
    $registry->register($this->plugin('two', ['required' => ['one' => '*']]));
    $this->assertFalse($registry->isActive('one'));
    $this->expectException(RuntimeException::class);
    (new PluginDependencies)->assertAcyclic($registry->all());
  }

  public function test_self_dependency_is_rejected(): void
  {
    $this->expectException(RuntimeException::class);
    $this->plugin('one', ['required' => ['one' => '*']]);
  }
}
