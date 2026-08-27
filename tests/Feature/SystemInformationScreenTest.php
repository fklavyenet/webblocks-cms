<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Support\System\SystemInformation;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Tests\TestCase;

class SystemInformationScreenTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);

    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  #[Test]
  public function the_route_requires_system_access(): void
  {
    $route = app('router')->getRoutes()->getByName('admin.system.information');

    $this->assertNotNull($route);
    $this->assertContains('can:access-system', $route->gatherMiddleware());
  }

  #[Test]
  public function the_provider_exposes_only_the_expected_non_sensitive_values(): void
  {
    $information = app(SystemInformation::class)->rows();

    $this->assertSame([
      'cms_version',
      'php_version',
      'laravel_version',
      'environment',
      'debug_mode',
      'database_driver',
      'default_locale',
      'admin_locale',
      'timezone',
    ], array_keys($information));

    $serialized = strtolower(json_encode($information, JSON_THROW_ON_ERROR));
    $this->assertStringNotContainsString('password', $serialized);
    $this->assertStringNotContainsString('token', $serialized);
    $this->assertStringNotContainsString('secret', $serialized);
  }

  #[Test]
  public function the_screen_uses_a_semantically_scoped_table(): void
  {
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/system/information.blade.php');

    $this->assertStringContainsString('<table ', $view);
    $this->assertStringContainsString('<th scope="col">', $view);
    $this->assertStringContainsString('<th scope="row">', $view);
  }

  #[Test]
  public function every_admin_locale_contains_the_help_and_system_information_copy(): void
  {
    foreach (AdminLocaleResolver::SUPPORTED_LOCALES as $locale) {
      $catalog = require dirname(__DIR__, 2).'/resources/lang/'.$locale.'/admin.php';

      $this->assertNotEmpty($catalog['navigation']['help'] ?? null, $locale);
      $this->assertNotEmpty($catalog['navigation']['documentation'] ?? null, $locale);
      $this->assertNotEmpty($catalog['navigation']['system_information'] ?? null, $locale);
      $this->assertNotEmpty($catalog['navigation']['developed_by'] ?? null, $locale);
      $this->assertSame(
        ['title', 'description', 'property', 'value', 'cms_version', 'php_version', 'laravel_version', 'environment', 'debug_mode', 'database_driver', 'default_locale', 'admin_locale', 'timezone', 'enabled', 'disabled'],
        array_keys($catalog['system_information'] ?? []),
        $locale,
      );
    }
  }
}
