<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce;

use WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\WebBlocksCommerceHealth;
use WebBlocks\Cms\Support\Plugins\PluginBlockTypeDefinition;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginMenuItem;
use WebBlocks\Cms\Support\Plugins\PluginPermission;
use WebBlocks\Cms\Support\Plugins\PluginSettingsDefinition;

class WebBlocksCommercePlugin
{
  public const HANDLE = 'webblocks-commerce';

  public static function definition(): PluginDefinition
  {
    return PluginDefinition::make(self::HANDLE)
      ->label('WebBlocks Commerce')
      ->version('0.1.1')
      ->provider(self::class)
      ->description('Simple product sales and hosted checkout foundations for WebBlocks CMS sites.')
      ->requiresCms('^1.32')
      ->settingsNamespace('webblocks_commerce')
      ->databasePrefix('webblocks_commerce_')
      ->permissions([
        PluginPermission::make('webblocks-commerce.view')
          ->label('View commerce')
          ->description('View commerce setup, products, orders, and payment status.'),
        PluginPermission::make('webblocks-commerce.manage')
          ->label('Manage commerce')
          ->description('Manage commerce setup and settings.'),
        PluginPermission::make('webblocks-commerce.manage-products')
          ->label('Manage commerce products')
          ->description('Create and edit simple commerce products.'),
        PluginPermission::make('webblocks-commerce.manage-orders')
          ->label('Manage commerce orders')
          ->description('View orders and payment attempts.'),
        PluginPermission::make('webblocks-commerce.manage-settings')
          ->label('Manage commerce settings')
          ->description('Review checkout gateway readiness and plugin setup.'),
      ])
      ->menu([
        PluginMenuItem::make('commerce-products')
          ->label('Commerce Products')
          ->route('webblocks.plugins.webblocks_commerce.products.index')
          ->icon('wb-icon-package')
          ->permission('webblocks-commerce.view')
          ->group('Content')
          ->sort(70),
        PluginMenuItem::make('commerce-orders')
          ->label('Commerce Orders')
          ->route('webblocks.plugins.webblocks_commerce.orders.index')
          ->icon('wb-icon-receipt')
          ->permission('webblocks-commerce.manage-orders')
          ->group('Content')
          ->sort(71),
        PluginMenuItem::make('commerce-settings')
          ->label('Commerce Settings')
          ->route('webblocks.plugins.webblocks_commerce.settings.edit')
          ->icon('wb-icon-settings')
          ->permission('webblocks-commerce.manage-settings')
          ->group('Content')
          ->sort(72),
      ])
      ->blockTypes([
        PluginBlockTypeDefinition::make('webblocks-commerce::buy-button')
          ->label('Commerce Buy Button')
          ->description('Links visitors to a WebBlocks Commerce product buy page.')
          ->adminView('webblocks-cms::admin.blocks.types.webblocks-commerce-buy-button')
          ->publicView('webblocks-cms::pages.partials.blocks.webblocks-commerce-buy-button')
          ->metadata([
            'catalog_slug' => 'webblocks-commerce-buy-button',
          ]),
      ])
      ->adminRoutes(dirname(__DIR__).'/routes/webblocks-commerce.php')
      ->migrations([
        'database/migrations',
      ])
      ->settings(
        PluginSettingsDefinition::make('webblocks.plugins.webblocks_commerce.settings.edit')
          ->label('Commerce Settings')
          ->description('Review checkout mode, currency, gateway configuration, and webhook readiness. Payment secrets are read from environment config and are never displayed.')
      )
      ->health(WebBlocksCommerceHealth::class);
  }
}
