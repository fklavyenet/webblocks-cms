<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\TestCase;

class PluginUpdateExperienceTest extends TestCase
{
  public function test_update_modal_receives_and_renders_safe_release_information(): void
  {
    $root = dirname(__DIR__, 2);
    $controller = (string) file_get_contents($root.'/src/Http/Controllers/Admin/SystemPluginController.php');
    $view = (string) file_get_contents($root.'/resources/views/admin/system/plugins/index.blade.php');

    foreach (['summary', 'notes', 'highlights'] as $field) {
      $this->assertStringContainsString("'{$field}' => \$plugin->latestCompatibleRelease->{$field}", $controller);
      $this->assertStringContainsString("\$plugin['catalog_update']['{$field}']", $view);
    }

    $this->assertStringContainsString("'details_url' => \$plugin->latestCompatibleRelease->detailsUrl", $controller);
    $this->assertStringContainsString("\$plugin['catalog_update']['details_url']", $view);

    $this->assertStringContainsString("systemPluginsIndexText('update_release_notes')", $view);
    $this->assertStringContainsString("systemPluginsIndexText('update_release_notes_unavailable')", $view);
    $this->assertStringContainsString("systemPluginsIndexText('update_release_notes_link')", $view);
    $this->assertStringContainsString('rel="noopener noreferrer"', $view);
    $this->assertStringNotContainsString('{!! $plugin[\'catalog_update\']', $view);
  }

  public function test_catalog_update_clears_compiled_views_without_rebuilding_runtime_registries(): void
  {
    $controller = (string) file_get_contents(dirname(__DIR__, 2).'/src/Http/Controllers/Admin/SystemPluginController.php');

    $this->assertStringContainsString('Every class from the installed version may already be loaded', $controller);
    $this->assertStringContainsString('$this->runtimeRefresher->refreshInstalledPackageAssets(', $controller);
    $this->assertStringContainsString('$this->runtimeRefresher->clearCompiledViews();', $controller);
    $this->assertStringNotContainsString('refresh(clearOptimizedCaches: true, registerRoutes:', $controller);
  }
}
