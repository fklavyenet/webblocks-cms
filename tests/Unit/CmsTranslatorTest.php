<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Support\Translations\CmsTranslator;

class CmsTranslatorTest extends TestCase
{
  #[Test]
  public function documented_cms_trans_helper_resolves_core_translation_keys(): void
  {
    $this->assertTrue(function_exists('cms_trans'));
    $this->assertSame('Dashboard', cms_trans('admin.navigation.dashboard', locale: 'en'));
  }

  #[Test]
  public function plugin_translator_resolves_plugin_namespaced_keys_with_locale_fallback(): void
  {
    app('translator')->addNamespace('webblocks-commerce', base_path('plugins/webblocks-commerce/resources/lang'));

    $translator = app(CmsTranslator::class);

    $this->assertSame(
      'Checkout Hazırlığı',
      $translator->plugin('webblocks-commerce', 'admin.settings.checkout_readiness', 'tr-TR')
    );

    $this->assertSame(
      'Missing plugin copy',
      $translator->plugin('webblocks-commerce', 'admin.settings.missing_plugin_copy', 'tr', fallback: 'Missing plugin copy')
    );
  }
}
