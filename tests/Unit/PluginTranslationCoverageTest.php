<?php

namespace WebBlocks\Cms\Tests\Unit;

use ReflectionMethod;
use RuntimeException;
use WebBlocks\Cms\Support\Plugins\PluginZipInstaller;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Tests\TestCase;

class PluginTranslationCoverageTest extends TestCase
{
  public function test_every_cms_admin_locale_must_mirror_the_english_catalogues(): void
  {
    $entries = ['webblocks-plugin.json', 'resources/lang/en/admin.php'];

    foreach (AdminLocaleResolver::SUPPORTED_LOCALES as $locale) {
      $entries[] = "resources/lang/{$locale}/admin.php";
    }

    $method = new ReflectionMethod(PluginZipInstaller::class, 'validateAdminTranslations');
    $method->invoke(new PluginZipInstaller, $entries, '');

    $this->addToAssertionCount(1);
  }

  public function test_a_missing_supported_locale_rejects_the_package(): void
  {
    $entries = ['webblocks-plugin.json', 'resources/lang/en/admin.php'];

    foreach (array_diff(AdminLocaleResolver::SUPPORTED_LOCALES, ['fr']) as $locale) {
      $entries[] = "resources/lang/{$locale}/admin.php";
    }

    $method = new ReflectionMethod(PluginZipInstaller::class, 'validateAdminTranslations');

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('missing the fr translation catalogue for admin.php');
    $method->invoke(new PluginZipInstaller, $entries, '');
  }
}
