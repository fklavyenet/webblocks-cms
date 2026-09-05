<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Support\System\Updates\CmsPublisherClientConfigurator;
use WebBlocks\Cms\Support\Updates\Client\Support\Version\ConfigVersionResolver;
use WebBlocks\Cms\Support\Updates\Client\Support\Version\VersionResolver;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateServerClient;
use WebBlocks\Cms\Tests\TestCase;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class CmsPublisherClientCoexistenceTest extends TestCase
{
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
    $this->assertSame('1.78.10', app(VersionResolver::class)->current());
    $this->assertNotSame('/wrong-product', config('publisher-client.apply.target_path'));
    $this->assertSame('app/system-updates', config('publisher-client.apply.workspace_root'));
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
