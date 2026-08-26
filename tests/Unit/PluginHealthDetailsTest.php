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

  public function test_plugin_views_share_the_health_check_table(): void
  {
    $root = dirname(__DIR__, 2);
    $index = file_get_contents($root.'/resources/views/admin/system/plugins/index.blade.php');
    $show = file_get_contents($root.'/resources/views/admin/system/plugins/show.blade.php');

    $this->assertStringContainsString('data-wb-target="#{{ $healthModalId }}"', $index);
    $this->assertStringContainsString('plugins.partials.health-checks', $index);
    $this->assertStringContainsString('plugins.partials.health-checks', $show);
  }
}
