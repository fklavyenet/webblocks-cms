<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Support\System\Updates\CmsPublisherClientConfigurator;
use WebBlocks\Cms\Support\Updates\Client\Support\Version\ConfigVersionResolver;
use WebBlocks\Cms\Support\Updates\Client\Support\Version\VersionResolver;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateServerClient;
use WebBlocks\Cms\Support\WebBlocks;
use WebBlocks\Cms\Tests\TestCase;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class CmsPublisherClientCoexistenceTest extends TestCase
{
  public static function translationCollisions(): iterable
  {
    foreach (['en', 'tr', 'de', 'fr', 'es', 'it'] as $locale) {
      foreach ([false, true] as $cached) {
        yield $locale.($cached ? '-cached' : '-unloaded') => [$locale, $cached];
      }
    }
  }

  #[Test]
  #[DataProvider('translationCollisions')]
  public function cms_restores_its_update_translations_after_an_older_product_claims_the_namespace(string $locale, bool $cached): void
  {
    $directory = sys_get_temp_dir().'/cms-client-translations-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($directory.'/'.$locale);
    File::put($directory.'/'.$locale.'/updates.php', "<?php return ['title' => 'Older updates'];");
    $translator = app('translator');
    $translator->addNamespace('publisher-client', $directory);
    $translator->setLoaded([]);
    $translator->addLines(['messages.retained' => 'Unrelated translation'], $locale, 'another-product');
    if ($cached) {
      $this->assertSame('Older updates', $translator->get('publisher-client::updates.title', [], $locale));
      $this->assertSame('publisher-client::updates.elapsed', $translator->get('publisher-client::updates.elapsed', [], $locale));
    }

    try {
      app(CmsPublisherClientConfigurator::class)->configure();
      app(CmsPublisherClientConfigurator::class)->configure();

      $expected = require dirname(__DIR__, 2).'/src/Support/Updates/Client/Resources/lang/'.$locale.'/updates.php';
      foreach (['title', 'elapsed', 'progress.running', 'progress.return', 'progress.success', 'progress.failure_message', 'phases.download'] as $key) {
        $this->assertSame(data_get($expected, $key), $translator->get('publisher-client::updates.'.$key, [], $locale));
      }
      $this->assertSame('Unrelated translation', $translator->get('another-product::messages.retained', [], $locale));
    } finally {
      File::deleteDirectory($directory);
    }
  }

  #[Test]
  public function cms_retains_installation_translation_overrides_while_restoring_its_catalog(): void
  {
    $directory = sys_get_temp_dir().'/cms-client-overrides-'.bin2hex(random_bytes(6));
    $translator = app('translator');
    $translator->getLoader()->addPath($directory);
    try {
      foreach (['en', 'tr', 'de', 'fr', 'es', 'it'] as $locale) {
        $path = $directory.'/vendor/publisher-client/'.$locale;
        File::ensureDirectoryExists($path);
        File::put($path.'/updates.php', "<?php return ['elapsed' => 'Installation elapsed'];");
        $translator->addLines(['updates.elapsed' => 'Foreign elapsed'], $locale, 'publisher-client');
      }

      app(CmsPublisherClientConfigurator::class)->configure();

      foreach (['en', 'tr', 'de', 'fr', 'es', 'it'] as $locale) {
        $expected = require dirname(__DIR__, 2).'/src/Support/Updates/Client/Resources/lang/'.$locale.'/updates.php';
        $this->assertSame('Installation elapsed', $translator->get('publisher-client::updates.elapsed', [], $locale));
        $this->assertSame($expected['progress']['running'], $translator->get('publisher-client::updates.progress.running', [], $locale));
      }
    } finally {
      File::deleteDirectory($directory);
    }
  }

  #[Test]
  public function cms_restores_its_namespaced_runtime_after_another_product_overwrites_shared_config(): void
  {
    config()->set('publisher-client.product', 'another-product');
    config()->set('publisher-client.version.resolver', 'Vendor\\Product\\Updates\\ConfigVersionResolver');
    config()->set('publisher-client.version.source', '1.20.0');
    config()->set('publisher-client.apply.target_path', '/wrong-product');
    config()->set('publisher-client.apply.workspace_root', 'app/wrong-product');
    config()->set('publisher-client.apply.package_validation.forbidden_content_patterns', ['WebBlocks\\Cms']);
    config()->set('publisher-client.apply.package_validation.content_scan_excluded_paths', ['src/ForeignRuntime']);

    app(CmsPublisherClientConfigurator::class)->configure();

    $this->assertSame('webblocks-cms', config('publisher-client.product'));
    $this->assertSame(ConfigVersionResolver::class, config('publisher-client.version.resolver'));
    $this->assertSame(WebBlocks::version(), app(VersionResolver::class)->current());
    $this->assertNotSame('/wrong-product', config('publisher-client.apply.target_path'));
    $this->assertSame('app/system-updates', config('publisher-client.apply.workspace_root'));
    $this->assertContains('LICENSE', config('publisher-client.apply.package_validation.allowed_roots'));
    $this->assertNotContains('docs', config('publisher-client.apply.package_validation.allowed_roots'));
    $this->assertContains('LICENSE', config('publisher-client.apply.package_validation.required_paths'));
    $this->assertSame(
      [],
      config('publisher-client.apply.package_file_promotions'),
    );
    $this->assertSame([], config('publisher-client.apply.package_validation.forbidden_content_patterns'));
    $this->assertSame([], config('publisher-client.apply.package_validation.content_scan_excluded_paths'));
    $this->assertInstanceOf(UpdateServerClient::class, app(UpdateServerClient::class));
  }

  #[Test]
  public function cms_boot_configuration_does_not_replace_an_already_active_product(): void
  {
    config()->set('publisher-client.product', 'another-product');
    config()->set('publisher-client.version.resolver', 'Vendor\\Product\\Updates\\ConfigVersionResolver');

    $provider = new class(app()) extends WebBlocksCmsServiceProvider
    {
      public function configurePublisherClientForTest(): void
      {
        $this->configureEmbeddedPublisherClient();
      }
    };

    $provider->configurePublisherClientForTest();

    $this->assertSame('another-product', config('publisher-client.product'));
    $this->assertSame('Vendor\\Product\\Updates\\ConfigVersionResolver', config('publisher-client.version.resolver'));
  }
}
