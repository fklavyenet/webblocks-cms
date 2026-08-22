<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WebBlocks\Cms\Http\Controllers\Admin\SystemSettingsController;

class SystemSettingsTabsStructureTest extends TestCase
{
  #[Test]
  public function it_presents_system_settings_sections_as_webblocks_ui_tabs(): void
  {
    $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/system/settings.blade.php');

    $this->assertStringContainsString('class="wb-tabs" data-wb-tabs', $view);
    $this->assertStringContainsString('class="wb-tabs-nav" role="tablist"', $view);
    $this->assertStringContainsString('data-wb-tab="system-settings-{{ $tabKey }}-panel"', $view);

    preg_match_all('/id="system-settings-([a-z]+)-panel"/', $view, $matches);

    $this->assertSame(['general', 'project', 'mail', 'privacy', 'runtime'], $matches[1]);
  }

  #[Test]
  public function it_returns_to_the_relevant_tab_after_system_settings_actions(): void
  {
    $controller = (string) file_get_contents((new ReflectionClass(SystemSettingsController::class))->getFileName());

    $this->assertStringContainsString("['tab' => \$request->validated('section')]", $controller);
    $this->assertStringContainsString("['tab' => 'mail']", $controller);
  }
}
