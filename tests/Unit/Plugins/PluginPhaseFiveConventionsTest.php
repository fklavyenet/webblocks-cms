<?php

namespace Tests\Unit\Plugins;

use PHPUnit\Framework\Attributes\Test;
use Tests\Fixtures\Plugins\DuplicatePluginCommand;
use Tests\Fixtures\Plugins\InvalidPluginCommand;
use Tests\Fixtures\Plugins\ValidPluginCommand;
use Tests\TestCase;
use WebBlocks\Cms\Support\Plugins\PluginCommandRegistrar;
use WebBlocks\Cms\Support\Plugins\PluginDashboardWidget;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginException;
use WebBlocks\Cms\Support\Plugins\PluginHealthMonitor;
use WebBlocks\Cms\Support\Plugins\PluginPublicAsset;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;

class PluginPhaseFiveConventionsTest extends TestCase
{
  #[Test]
  public function compatible_enabled_plugins_are_active_and_expose_metadata(): void
  {
    $registry = new PluginRegistry(['ecosystem-tools' => true]);
    $registry->register(
      PluginDefinition::make('ecosystem-tools')
        ->label('Ecosystem Tools')
        ->version('1.0.0')
        ->requiresCms('^1.32')
        ->settingsNamespace('ecosystem_tools')
        ->databasePrefix('ecosystem_tools_')
        ->commands([ValidPluginCommand::class])
    );

    $summary = $registry->summaries()[0];

    $this->assertTrue($registry->isEnabled('ecosystem-tools'));
    $this->assertSame('enabled', $summary['lifecycle_status']);
    $this->assertSame('ecosystem_tools', $summary['settings_namespace']);
    $this->assertSame('ecosystem_tools_', $summary['database_prefix']);
    $this->assertSame([ValidPluginCommand::class], (new PluginCommandRegistrar($registry))->enabledCommands());
  }

  #[Test]
  public function incompatible_plugins_are_reported_and_remain_inert_even_when_configured_enabled(): void
  {
    $registry = new PluginRegistry(['future-plugin' => true]);
    $registry->register(
      PluginDefinition::make('future-plugin')
        ->label('Future Plugin')
        ->requiresCms('>=99.0.0')
        ->dashboardWidgets([
          PluginDashboardWidget::make('future-plugin.status')->title('Future Status'),
        ])
        ->publicAssets([
          PluginPublicAsset::cssHead('future-plugin.public-css', '/cms/plugins/future-plugin/public.css'),
        ])
    );

    $summary = $registry->summaries()[0];
    $health = (new PluginHealthMonitor($registry))->healthFor($registry->get('future-plugin'));

    $this->assertFalse($registry->isEnabled('future-plugin'));
    $this->assertTrue($summary['configured_enabled']);
    $this->assertFalse($summary['compatible']);
    $this->assertSame('incompatible', $summary['lifecycle_status']);
    $this->assertSame([], $registry->dashboardWidgets());
    $this->assertSame([], $registry->publicAssets());
    $this->assertSame('incompatible', $health->status);
    $this->assertStringContainsString('Requires WebBlocks CMS >=99.0.0', $health->message);
  }

  #[Test]
  public function plugin_command_names_must_be_handle_prefixed(): void
  {
    $registry = new PluginRegistry(['ecosystem-tools' => true]);

    $this->expectException(PluginException::class);
    $this->expectExceptionMessage('must start with [ecosystem-tools:]');

    $registry->register(
      PluginDefinition::make('ecosystem-tools')
        ->label('Ecosystem Tools')
        ->commands([InvalidPluginCommand::class])
    );
  }

  #[Test]
  public function plugin_command_names_must_not_collide(): void
  {
    $registry = new PluginRegistry(['ecosystem-tools' => true]);

    $this->expectException(PluginException::class);
    $this->expectExceptionMessage('command [ecosystem-tools:sync] is already registered');

    $registry->register(
      PluginDefinition::make('ecosystem-tools')
        ->label('Ecosystem Tools')
        ->commands([ValidPluginCommand::class, DuplicatePluginCommand::class])
    );
  }

  #[Test]
  public function plugin_database_prefixes_must_not_collide(): void
  {
    $registry = new PluginRegistry;
    $registry->register(PluginDefinition::make('ecosystem-tools')->label('Ecosystem Tools'));

    $this->expectException(PluginException::class);
    $this->expectExceptionMessage('database prefix [ecosystem_tools_] is already registered');

    $registry->register(
      PluginDefinition::make('ecosystem-tools-copy')
        ->label('Ecosystem Tools Copy')
        ->databasePrefix('ecosystem_tools_')
    );
  }
}
