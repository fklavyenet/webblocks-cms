<?php

namespace Tests\Unit\Plugins;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginException;
use WebBlocks\Cms\Support\Plugins\PluginMenuItem;
use WebBlocks\Cms\Support\Plugins\PluginPermission;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;

class PluginRegistryTest extends TestCase
{
  #[Test]
  public function registry_registers_and_returns_plugins_by_handle(): void
  {
    $plugin = $this->samplePlugin();
    $registry = new PluginRegistry(['webblocks-ui-manager' => true]);

    $registry->register($plugin);

    $this->assertTrue($registry->has('webblocks-ui-manager'));
    $this->assertSame('webblocks-ui-manager', $registry->get('webblocks-ui-manager')?->handle());
    $this->assertSame(['webblocks-ui-manager'], array_keys($registry->all()));
    $this->assertSame(['webblocks-ui-manager'], array_keys($registry->enabled()));
    $this->assertSame([], $registry->disabled());
  }

  #[Test]
  public function duplicate_handles_are_rejected(): void
  {
    $registry = new PluginRegistry;
    $registry->register($this->samplePlugin());

    $this->expectException(PluginException::class);
    $this->expectExceptionMessage('already registered');

    $registry->register($this->samplePlugin());
  }

  #[Test]
  public function plugins_are_disabled_by_default_when_config_is_absent(): void
  {
    $plugin = $this->samplePlugin();
    $registry = new PluginRegistry;

    $registry->register($plugin);

    $this->assertSame([], $registry->enabled());
    $this->assertSame(['webblocks-ui-manager'], array_keys($registry->disabled()));
    $this->assertFalse($registry->isEnabled('webblocks-ui-manager'));
  }

  #[Test]
  public function menu_items_exclude_disabled_plugins(): void
  {
    $registry = new PluginRegistry([
      'webblocks-ui-manager' => true,
      'disabled-plugin' => false,
    ]);

    $registry->register($this->samplePlugin());
    $registry->register(
      PluginDefinition::make('disabled-plugin')
        ->label('Disabled Plugin')
        ->menu([
          PluginMenuItem::make('tools')
            ->label('Tools')
            ->route('webblocks.plugins.disabled_plugin.tools.index')
            ->permission('disabled-plugin.view'),
        ])
        ->permissions([
          PluginPermission::make('disabled-plugin.view')->label('View disabled plugin'),
        ])
    );

    $items = $registry->menuItems();

    $this->assertCount(1, $items);
    $this->assertSame('webblocks-ui-manager', $items[0]['plugin']->handle());
    $this->assertSame('releases', $items[0]['item']->key());
  }

  #[Test]
  public function permissions_are_grouped_by_enabled_and_disabled_state(): void
  {
    $registry = new PluginRegistry([
      'webblocks-ui-manager' => true,
      'disabled-plugin' => false,
    ]);

    $registry->register($this->samplePlugin());
    $registry->register(
      PluginDefinition::make('disabled-plugin')
        ->label('Disabled Plugin')
        ->permissions([
          PluginPermission::make('disabled-plugin.view')->label('View disabled plugin'),
        ])
    );

    $this->assertArrayHasKey('webblocks-ui-manager', $registry->permissions());
    $this->assertArrayNotHasKey('disabled-plugin', $registry->permissions());
    $this->assertArrayHasKey('disabled-plugin', $registry->disabledPermissions());
  }

  #[Test]
  public function summaries_return_copyable_arrays_in_label_order(): void
  {
    $registry = new PluginRegistry(['webblocks-ui-manager' => true]);
    $registry->register($this->samplePlugin());

    $summaries = $registry->summaries();
    $summaries[0]['label'] = 'Changed outside registry';

    $this->assertSame('WebBlocks UI Manager', $registry->summaries()[0]['label']);
    $this->assertTrue($registry->summaries()[0]['enabled']);
  }

  #[Test]
  public function registry_snapshots_do_not_expose_mutable_internal_plugin_state(): void
  {
    $plugin = $this->samplePlugin();
    $registry = new PluginRegistry;

    $registry->register($plugin);
    $plugin->label('Changed after registration');
    $registry->get('webblocks-ui-manager')?->label('Changed from snapshot');

    $this->assertSame('WebBlocks UI Manager', $registry->summaries()[0]['label']);
  }

  private function samplePlugin(): PluginDefinition
  {
    return PluginDefinition::make('webblocks-ui-manager')
      ->label('WebBlocks UI Manager')
      ->version('0.1.0')
      ->description('Publishes WebBlocks UI release artifacts to first-party CDN.')
      ->menu([
        PluginMenuItem::make('releases')
          ->label('Releases')
          ->route('webblocks.plugins.webblocks_ui_manager.releases.index')
          ->icon('wb-icon-box')
          ->permission('webblocks-ui-manager.view'),
      ])
      ->permissions([
        PluginPermission::make('webblocks-ui-manager.view')->label('View WebBlocks UI releases'),
        PluginPermission::make('webblocks-ui-manager.publish')->label('Publish WebBlocks UI releases'),
      ]);
  }
}
