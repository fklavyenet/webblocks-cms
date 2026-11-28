<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Support\Plugins\PluginHealthResult;

class PluginHealthDetailsTest extends TestCase
{
  public function test_health_result_serializes_individual_checks(): void
  {
    $result = PluginHealthResult::withChecks('warning', 'Needs attention.', [
      ['name' => 'Mail transport', 'status' => 'warning', 'message' => 'Not configured.'],
    ]);

    $this->assertSame([
      'status' => 'warning',
      'message' => 'Needs attention.',
      'checks' => [
        ['name' => 'Mail transport', 'status' => 'warning', 'message' => 'Not configured.'],
      ],
    ], $result->toArray());
  }

  public function test_plugin_views_show_lifecycle_without_health_badges_or_dialogs(): void
  {
    $root = dirname(__DIR__, 2);
    foreach (['index', 'show'] as $name) {
      $view = file_get_contents($root.'/resources/views/admin/system/plugins/'.$name.'.blade.php');
      $this->assertStringNotContainsString("['health']", $view);
      $this->assertStringNotContainsString('plugin-health-', $view);
      $this->assertStringNotContainsString('view_health_details', $view);
      $this->assertStringContainsString("['lifecycle_label']", $view);
    }
  }
}
