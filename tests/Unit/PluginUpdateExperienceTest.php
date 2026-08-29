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

    $this->assertStringContainsString("systemPluginsIndexText('update_release_notes')", $view);
    $this->assertStringContainsString("systemPluginsIndexText('update_release_notes_unavailable')", $view);
    $this->assertStringNotContainsString('{!! $plugin[\'catalog_update\']', $view);
  }

  public function test_catalog_update_defers_new_route_registration_to_the_next_request(): void
  {
    $controller = (string) file_get_contents(dirname(__DIR__, 2).'/src/Http/Controllers/Admin/SystemPluginController.php');

    $this->assertStringContainsString('refresh(clearOptimizedCaches: true, registerRoutes: false)', $controller);
    $this->assertStringNotContainsString('refresh(clearOptimizedCaches: true, registerRoutes: true)', $controller);
  }
}
