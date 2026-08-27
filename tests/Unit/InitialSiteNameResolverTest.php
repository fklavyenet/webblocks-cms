<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Support\Install\InitialSiteNameResolver;
use WebBlocks\Cms\Tests\TestCase;

class InitialSiteNameResolverTest extends TestCase
{
  #[Test]
  public function it_prefers_an_explicit_command_name(): void
  {
    config()->set('webblocks-cms.defaults.site_name', 'Configured Site');
    config()->set('app.url', 'https://example.com');

    $this->assertSame('Command Site', app(InitialSiteNameResolver::class)->resolve(' Command Site '));
  }

  #[Test]
  public function it_prefers_a_configured_default_name(): void
  {
    config()->set('webblocks-cms.defaults.site_name', 'Configured Site');
    config()->set('app.url', 'https://example.com');

    $this->assertSame('Configured Site', app(InitialSiteNameResolver::class)->resolve());
  }

  #[Test]
  public function it_uses_the_normalized_app_host_by_default(): void
  {
    config()->set('webblocks-cms.defaults.site_name');
    config()->set('app.url', 'https://WWW.HerneStore.com./shop');

    $this->assertSame('hernestore.com', app(InitialSiteNameResolver::class)->resolve());
  }

  #[Test]
  public function it_preserves_meaningful_subdomains(): void
  {
    config()->set('webblocks-cms.defaults.site_name');
    config()->set('app.url', 'https://shop.hernestore.com');

    $this->assertSame('shop.hernestore.com', app(InitialSiteNameResolver::class)->resolve());
  }

  #[Test]
  public function it_falls_back_for_local_and_ip_hosts(): void
  {
    config()->set('webblocks-cms.defaults.site_name');

    config()->set('app.url', 'http://localhost');
    $this->assertSame('Default Site', app(InitialSiteNameResolver::class)->resolve());

    config()->set('app.url', 'http://127.0.0.1');
    $this->assertSame('Default Site', app(InitialSiteNameResolver::class)->resolve());
  }
}
