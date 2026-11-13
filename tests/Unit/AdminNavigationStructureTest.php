<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Models\Site;

class AdminNavigationStructureTest extends TestCase
{
  #[Test]
  public function assets_remains_a_content_destination_outside_site_settings(): void
  {
    $layout = (string) file_get_contents(dirname(__DIR__, 2).'/src/Support/Admin/AdminNavigation.php');
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
  public function help_is_a_direct_link_after_the_groups_and_health_replaces_system_information(): void
  {
    $layout = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/admin.blade.php');
    $maintenance = strpos($layout, 'AdminNavigation::class');
    $appendHelp = strpos($layout, 'href="{{ app(DocumentationUrlResolver::class)->url($adminLocale) }}"');

    $this->assertNotFalse($maintenance);
    $this->assertNotFalse($appendHelp);
    $this->assertGreaterThan($maintenance, $appendHelp);
    $this->assertStringNotContainsString("'key' => 'help'", $layout);
    $this->assertStringNotContainsString("'route' => 'admin.system.information'", $layout);
    $this->assertStringContainsString("'route' => 'admin.system.health.index'", (string) file_get_contents(dirname(__DIR__, 2).'/src/Support/Admin/AdminNavigation.php'));
  }

  #[Test]
  public function site_scoped_navigation_uses_explicit_role_abilities(): void
  {
    $layout = (string) file_get_contents(dirname(__DIR__, 2).'/src/Support/Admin/AdminNavigation.php');
    $systemGroup = strpos($layout, "'key' => 'system'");
    $visitorReports = strpos($layout, "'route' => 'admin.reports.visitors.index'");

    $this->assertStringContainsString("can('manage-site-operations')", $layout);
    $this->assertStringContainsString("can('view-visitor-reports')", $layout);
    $this->assertNotFalse($visitorReports);
    $this->assertNotFalse($systemGroup);
    $this->assertLessThan($systemGroup, $visitorReports);
  }
}
