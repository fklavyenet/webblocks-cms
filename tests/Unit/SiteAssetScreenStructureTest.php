<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\TestCase;

class SiteAssetScreenStructureTest extends TestCase
{
  public function test_site_selector_uses_the_shared_listing_filter_standard(): void
  {
    $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/sites/assets.blade.php');

    $this->assertStringContainsString("@include('webblocks-cms::admin.partials.listing-filters'", $view);
    $this->assertStringContainsString("'name' => 'site'", $view);
    $this->assertStringContainsString("'applyLabel' => \$adminText('select_site')", $view);
    $this->assertStringNotContainsString('class="wb-cluster wb-cluster-2 wb-flex-wrap"', $view);
  }

  public function test_each_site_asset_editor_owns_its_submit_form(): void
  {
    $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/sites/partials/assets-tab.blade.php');

    $this->assertStringContainsString('<form method="POST" action="{{ route(\'admin.sites.assets.update\'', $view);
    $this->assertStringContainsString('name="contents"', $view);
    $this->assertStringContainsString('<button type="submit" class="wb-btn wb-btn-primary"', $view);
    $this->assertStringNotContainsString('form="{{ $formId }}"', $view);
  }

  public function test_asset_metadata_is_compact_and_does_not_repeat_the_url(): void
  {
    $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/sites/partials/assets-tab.blade.php');

    $this->assertSame(1, substr_count($view, '$asset[\'relative_path\']'));
    $this->assertStringNotContainsString('$asset[\'public_path\']', $view);
    $this->assertStringContainsString('$adminText(\'file_size\'', $view);
    $this->assertStringNotContainsString('class="wb-grid wb-grid-2 wb-gap-3"', $view);
  }

  public function test_site_edit_screen_does_not_carry_orphaned_asset_forms(): void
  {
    $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/sites/form.blade.php');

    $this->assertStringNotContainsString('site-asset-{{ $asset[\'type\'] }}-form', $view);
  }
}
