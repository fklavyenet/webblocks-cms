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

    preg_match_all('/id="system-settings-([a-z-]+)-panel"/', $view, $matches);

    $this->assertSame(['general', 'project', 'mail', 'privacy', 'backup-cleanup', 'runtime'], $matches[1]);
  }

  #[Test]
  public function it_returns_to_the_relevant_tab_after_system_settings_actions(): void
  {
    $controller = (string) file_get_contents((new ReflectionClass(SystemSettingsController::class))->getFileName());

    $this->assertStringContainsString("['tab' => \$request->validated('section')]", $controller);
    $this->assertStringContainsString("['tab' => 'mail']", $controller);
  }

  #[Test]
  public function mail_tools_open_as_overlays_and_test_errors_reopen_the_test_modal(): void
  {
    $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/system/settings.blade.php');

    $this->assertStringContainsString('data-wb-target="#settings-mail-diagnostics-modal"', $view);
    $this->assertStringContainsString('data-wb-target="#settings-mail-test-modal"', $view);
    $this->assertStringContainsString('id="settings-mail-diagnostics-modal" role="dialog"', $view);
    $this->assertStringContainsString('id="settings-mail-test-modal" role="dialog"', $view);
    $this->assertStringContainsString("\$errors->has('recipient_email') ? 'is-open' : ''", $view);
  }

  #[Test]
  public function backups_cleanup_action_stays_with_the_cleanup_status_and_uses_a_modal(): void
  {
    $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/system/backups/index.blade.php');

    $this->assertStringContainsString('data-wb-target="#backups-cleanup-run-modal"', $view);
    $this->assertStringContainsString("'action' => route('admin.system.backups.cleanup')", $view);
    $this->assertStringContainsString('@disabled($backupCleanupPreview->candidateCount() === 0)', $view);
  }
}
