<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Support\Plugins\Catalog\CatalogPlugin;

class CatalogPluginPresentationTest extends TestCase
{
  public function test_it_normalizes_pricing_artwork_categories_and_downloads(): void
  {
    $plugin = CatalogPlugin::fromArray([
      'handle' => 'webblocks-commerce',
      'name' => 'WebBlocks Commerce',
      'pricing' => [
        'type' => 'paid',
        'price_minor' => 4900,
        'currency' => 'USD',
        'billing_period' => 'year',
      ],
      'artwork' => [
        'card_url' => 'https://plugins.webblocksui.com/api/catalog-artwork/plugins/webblocks-commerce',
        'alt' => 'Commerce interface illustration',
      ],
      'categories' => [
        ['slug' => 'commerce', 'name' => 'Commerce'],
      ],
      'downloads' => [
        'total' => 1250,
        'daily' => [
          ['date' => '2026-09-18', 'downloads' => 12],
        ],
      ],
    ]);

    $this->assertNotNull($plugin);
    $this->assertSame('paid', $plugin->pricingType);
    $this->assertSame(4900, $plugin->priceMinor);
    $this->assertSame('USD', $plugin->priceCurrency);
    $this->assertSame('year', $plugin->billingPeriod);
    $this->assertSame(1250, $plugin->downloadsTotal);
    $this->assertSame([['date' => '2026-09-18', 'downloads' => 12]], $plugin->dailyDownloads);
    $this->assertSame([['slug' => 'commerce', 'name' => 'Commerce']], $plugin->categories);
    $this->assertSame('Commerce interface illustration', $plugin->artworkAlt);
  }

  public function test_legacy_catalog_payload_defaults_to_free_without_downloads(): void
  {
    $plugin = CatalogPlugin::fromArray([
      'handle' => 'legacy-plugin',
      'name' => 'Legacy Plugin',
    ]);

    $this->assertNotNull($plugin);
    $this->assertSame('free', $plugin->pricingType);
    $this->assertNull($plugin->priceMinor);
    $this->assertSame(0, $plugin->downloadsTotal);
    $this->assertSame([], $plugin->dailyDownloads);
  }
}
