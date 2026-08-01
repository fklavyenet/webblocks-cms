<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

/**
 * The Edit Page settings strip used to keep its "last active tab" hidden
 * field in sync via a hand-written page-assets.js listener on
 * wb:tabs:change. webblocks-ui 2.17.0 added data-wb-tabs-field to do this
 * natively, so the CMS-authored copy of that logic must not come back —
 * here or on any other form with tabs.
 */
class PageEditTabsStructureTest extends TestCase
{
  private function edit(): string
  {
    return (string) file_get_contents(
      dirname(__DIR__, 2).'/resources/views/admin/pages/edit.blade.php'
    );
  }

  #[Test]
  public function the_tab_strip_syncs_via_the_shipped_widget_attribute(): void
  {
    $edit = $this->edit();

    $this->assertStringContainsString('data-wb-tabs', $edit);
    $this->assertStringContainsString('data-wb-tabs-field="[data-wb-page-settings-tab-input]"', $edit);
    $this->assertStringContainsString('data-wb-page-settings-tab-input', $edit);
  }

  /**
   * data-wb-tabs-field syncs the tab BUTTON's panel id
   * ("page-management-{key}-panel"), not the bare key $settingsTab and
   * request('tab') use — old('_page_settings_tab') must unwrap it before
   * falling into the same known-value match as a fresh ?tab= link, or a
   * redisplay after a validation error reopens on the wrong tab (or worse,
   * silently falls through to "settings" every time).
   */
  #[Test]
  public function old_page_settings_tab_is_unwrapped_before_matching(): void
  {
    $edit = $this->edit();

    $this->assertStringContainsString(
      "str_replace(['page-management-', '-panel'], '', \$rawPageSettingsTab)",
      $edit,
      'old(\'_page_settings_tab\') carries a panel id now, not a bare key; it must be unwrapped before the match.'
    );
    $this->assertStringContainsString("'assets', 'page-assets' => 'assets',", $edit);
    $this->assertStringContainsString("'layout-slots' => 'layout-slots',", $edit);
    $this->assertStringContainsString("'overview' => 'overview',", $edit);
  }

  #[Test]
  public function no_cms_authored_tab_sync_script_exists_for_this_form(): void
  {
    $this->assertFileDoesNotExist(dirname(__DIR__, 2).'/public/cms/js/admin/page-assets.js');
    $this->assertStringNotContainsString('page-assets.js', $this->edit());
    $this->assertNotContains('cms/js/admin/page-assets.js', WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ASSET_FILES);
    $this->assertNotContains('cms/js/admin/page-assets.js', WebBlocksCmsServiceProvider::ROOT_PUBLIC_ASSET_COMPATIBILITY_FILES);
  }
}
