<?php

namespace Tests\Unit\Plugins;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Support\Plugins\PluginBlockPackDefinition;
use WebBlocks\Cms\Support\Plugins\PluginBlockRegistry;
use WebBlocks\Cms\Support\Plugins\PluginBlockTypeDefinition;
use WebBlocks\Cms\Support\Plugins\PluginDashboardWidget;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginException;
use WebBlocks\Cms\Support\Plugins\PluginPublicAsset;
use WebBlocks\Cms\Support\Plugins\PluginPublicAssetRegistry;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Plugins\PluginSystemCard;

class PluginExtensionRegistryTest extends TestCase
{
  #[Test]
  public function enabled_plugin_contributions_are_attributed_and_discoverable(): void
  {
    $registry = new PluginRegistry(['analytics-tools' => true]);
    $registry->register($this->phaseThreePlugin());

    $dashboardWidgets = $registry->dashboardWidgets();
    $systemCards = $registry->systemCards();
    $blockTypes = (new PluginBlockRegistry($registry))->discoverableBlockTypes();
    $assets = (new PluginPublicAssetRegistry($registry))->head();

    $this->assertSame('analytics-tools', $dashboardWidgets[0]->pluginHandle());
    $this->assertSame('analytics-tools.overview', $dashboardWidgets[0]->key());
    $this->assertSame('analytics-tools.status', $systemCards[0]->key());
    $this->assertArrayHasKey('analytics-tools::score-card', $blockTypes);
    $this->assertSame('analytics-tools', $blockTypes['analytics-tools::score-card']->pluginHandle());
    $this->assertSame('analytics-tools.public-css', $assets[0]->handle());
  }

  #[Test]
  public function disabled_plugins_contribute_nothing_to_phase_three_slots(): void
  {
    $registry = new PluginRegistry(['analytics-tools' => false]);
    $registry->register($this->phaseThreePlugin());

    $this->assertSame([], $registry->dashboardWidgets());
    $this->assertSame([], $registry->systemCards());
    $this->assertSame([], (new PluginBlockRegistry($registry))->discoverableBlockTypes());
    $this->assertSame([], (new PluginPublicAssetRegistry($registry))->head());
    $this->assertSame([], (new PluginPublicAssetRegistry($registry))->bodyEnd());
  }

  #[Test]
  public function public_assets_are_collected_by_safe_head_and_body_end_locations(): void
  {
    $registry = new PluginRegistry(['analytics-tools' => true]);
    $registry->register($this->phaseThreePlugin());

    $assetRegistry = new PluginPublicAssetRegistry($registry);

    $this->assertSame(['analytics-tools.public-css'], array_map(
      fn (PluginPublicAsset $asset): string => $asset->handle(),
      $assetRegistry->head()
    ));
    $this->assertSame(['analytics-tools.public-js'], array_map(
      fn (PluginPublicAsset $asset): string => $asset->handle(),
      $assetRegistry->bodyEnd()
    ));
  }

  #[Test]
  public function duplicate_dashboard_widget_keys_are_rejected(): void
  {
    $this->expectException(PluginException::class);
    $this->expectExceptionMessage('extension key [analytics-tools.overview] is already registered');

    PluginDefinition::make('analytics-tools')
        ->label('Analytics Tools Copy')
        ->dashboardWidgets([
          PluginDashboardWidget::make('analytics-tools.overview')->title('Overview'),
          PluginDashboardWidget::make('analytics-tools.overview')->title('Duplicate'),
        ]);
  }

  #[Test]
  public function plugin_owned_extension_keys_must_start_with_plugin_handle(): void
  {
    $this->expectException(PluginException::class);
    $this->expectExceptionMessage('must start with [analytics-tools.]');

    PluginDefinition::make('analytics-tools')
      ->label('Analytics Tools')
      ->systemCards([
        PluginSystemCard::make('other-plugin.status')->title('Status'),
      ]);
  }

  #[Test]
  public function plugin_block_handles_must_be_owned_namespaced_handles(): void
  {
    $this->expectException(PluginException::class);
    $this->expectExceptionMessage('must start with [analytics-tools::]');

    PluginDefinition::make('analytics-tools')
      ->label('Analytics Tools')
      ->blockTypes([
        PluginBlockTypeDefinition::make('other-plugin::score-card')->label('Score Card'),
      ]);
  }

  #[Test]
  public function core_block_handles_cannot_be_declared_as_plugin_blocks(): void
  {
    $this->expectException(PluginException::class);
    $this->expectExceptionMessage('must use plugin-handle::block-handle namespacing');

    PluginBlockTypeDefinition::make('hero')->label('Hero');
  }

  #[Test]
  public function duplicate_block_and_asset_handles_are_rejected(): void
  {
    $registry = new PluginRegistry(['analytics-tools' => true]);
    $registry->register(
      PluginDefinition::make('analytics-tools')
        ->label('Analytics Tools')
        ->blockTypes([
          PluginBlockTypeDefinition::make('analytics-tools::score-card')->label('Score Card'),
        ])
        ->blockPacks([
          PluginBlockPackDefinition::make('analytics-tools')
            ->label('Analytics Pack')
            ->blockTypes([
              PluginBlockTypeDefinition::make('analytics-tools::score-card')->label('Score Card'),
            ]),
        ])
    );

    $this->expectException(PluginException::class);
    $this->expectExceptionMessage('block handle [analytics-tools::score-card] is already registered');

    $registry->pluginBlockTypes();
  }

  #[Test]
  public function duplicate_public_asset_handles_are_rejected(): void
  {
    $this->expectException(PluginException::class);
    $this->expectExceptionMessage('asset handle [analytics-tools.public-css] is already registered');

    PluginDefinition::make('analytics-tools')
        ->label('Analytics Tools')
        ->publicAssets([
          PluginPublicAsset::cssHead('analytics-tools.public-css', '/cms/plugins/analytics-tools/public.css'),
          PluginPublicAsset::cssHead('analytics-tools.public-css', '/cms/plugins/analytics-tools/other.css'),
        ]);
  }

  private function phaseThreePlugin(): PluginDefinition
  {
    return PluginDefinition::make('analytics-tools')
      ->label('Analytics Tools')
      ->dashboardWidgets([
        PluginDashboardWidget::make('analytics-tools.overview')
          ->title('Analytics Overview')
          ->description('Read-only analytics summary.')
          ->value(42),
      ])
      ->systemCards([
        PluginSystemCard::make('analytics-tools.status')
          ->title('Analytics Status')
          ->description('Read-only system status.'),
      ])
      ->blockTypes([
        PluginBlockTypeDefinition::make('analytics-tools::score-card')
          ->label('Score Card')
          ->description('Plugin-owned block declaration.'),
      ])
      ->publicAssets([
        PluginPublicAsset::cssHead('analytics-tools.public-css', '/cms/plugins/analytics-tools/public.css'),
        PluginPublicAsset::jsBodyEnd('analytics-tools.public-js', '/cms/plugins/analytics-tools/public.js'),
      ]);
  }
}
