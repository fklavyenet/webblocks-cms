<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Models\Site;

class AdminNavigationStructureTest extends TestCase
{
  #[Test]
  public function assets_is_a_sidebar_destination_immediately_after_media(): void
  {
    $layout = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/admin.blade.php');
    $media = strpos($layout, "'route' => 'admin.media.index'");
    $assets = strpos($layout, "'route' => 'admin.site-assets.index'");
    $contactMessages = strpos($layout, "'route' => 'admin.contact-messages.index'");

    $this->assertNotFalse($media);
    $this->assertNotFalse($assets);
    $this->assertNotFalse($contactMessages);
    $this->assertTrue($media < $assets && $assets < $contactMessages);
    $this->assertNotContains('assets', Site::ADMIN_FORM_TABS);
    $this->assertStringNotContainsString('site-settings-assets-panel', (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/sites/form.blade.php'));
  }

  #[Test]
  public function help_is_appended_after_the_system_and_maintenance_groups(): void
  {
    $layout = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/admin.blade.php');
    $maintenance = strpos($layout, "'key' => 'maintenance'");
    $appendHelp = strpos($layout, '$sidebarGroups[] = $helpSidebarGroup;');

    $this->assertNotFalse($maintenance);
    $this->assertNotFalse($appendHelp);
    $this->assertGreaterThan($maintenance, $appendHelp);
  }

  #[Test]
  public function theme_menu_uses_the_shared_palette_and_chevron_trigger(): void
  {
    $layout = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/admin.blade.php');

    $this->assertStringContainsString('wb-theme-switcher wb-dropdown wb-dropdown-end', $layout);
    $this->assertStringContainsString('wb-theme-switcher-trigger', $layout);
    $this->assertStringContainsString('wb-icon-palette wb-theme-switcher-icon', $layout);
    $this->assertStringContainsString('wb-icon-chevron-down wb-theme-switcher-chevron', $layout);
  }
}
