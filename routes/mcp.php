<?php

use Illuminate\Support\Facades\Route;
use WebBlocks\Cms\Http\Controllers\Mcp\McpController;
use WebBlocks\Cms\Http\Controllers\Mcp\McpOAuthController;
use WebBlocks\Cms\Http\Middleware\CoalesceSearchIndexing;
use WebBlocks\Cms\Http\Middleware\RequireMcpConfiguration;
use WebBlocks\Cms\Http\Middleware\UseAdminLocale;
use WebBlocks\Cms\Support\Mcp\McpConfiguration;

Route::middleware([RequireMcpConfiguration::class, 'install.required'])->name('webblocks.mcp.')->group(function (): void {
  Route::get(McpConfiguration::RESOURCE_METADATA, [McpOAuthController::class, 'resourceMetadata'])->middleware('throttle:60,1,webblocks-mcp-metadata')->name('resource-metadata');
  Route::get(McpConfiguration::SERVER_METADATA, [McpOAuthController::class, 'serverMetadata'])->middleware('throttle:60,1,webblocks-mcp-metadata')->name('server-metadata');
  Route::match(['GET', 'POST'], McpConfiguration::PATH, McpController::class)
    ->middleware(['throttle:120,1,webblocks-mcp-server', CoalesceSearchIndexing::class])->name('server');
  Route::post(McpConfiguration::PATH.'/token', [McpOAuthController::class, 'token'])->middleware('throttle:30,1,webblocks-mcp-token')->name('token');
  Route::match(['GET', 'POST'], McpConfiguration::PATH.'/authorize', [McpOAuthController::class, 'authorize'])
    ->middleware(['web', 'admin.access', UseAdminLocale::class, 'throttle:30,1,webblocks-mcp-consent'])->name('authorize');
});
