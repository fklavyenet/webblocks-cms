<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class AdminUiOwnershipTest extends TestCase
{
  #[Test]
  public function password_visibility_is_owned_by_webblocks_ui(): void
  {
    $root = dirname(__DIR__, 2);

    $this->assertFileDoesNotExist($root.'/public/cms/js/admin/password-fields.js');
    $this->assertNotContains('cms/js/admin/password-fields.js', WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ASSET_FILES);
    $this->assertNotContains('cms/js/admin/password-fields.js', WebBlocksCmsServiceProvider::ROOT_PUBLIC_ASSET_COMPATIBILITY_FILES);

    foreach (['admin/users/form.blade.php', 'admin/profile/edit.blade.php'] as $view) {
      $source = (string) file_get_contents($root.'/resources/views/'.$view);

      $this->assertStringContainsString('data-wb-password-toggle', $source);
      $this->assertStringNotContainsString('password-fields.js', $source);
    }
  }

  #[Test]
  public function slot_source_selection_uses_the_shipped_button_check_primitive(): void
  {
    $root = dirname(__DIR__, 2);
    $view = (string) file_get_contents($root.'/resources/views/admin/pages/partials/slots-card.blade.php');
    $script = (string) file_get_contents($root.'/public/cms/js/admin/page-slot-source-modals.js');

    $this->assertStringContainsString('class="wb-btn-check-group"', $view);
    $this->assertStringContainsString('class="wb-btn-check"', $view);
    $this->assertStringNotContainsString('data-wb-slot-source-option', $view);
    $this->assertStringNotContainsString("classList.toggle('wb-btn-primary'", $script);
  }
}
