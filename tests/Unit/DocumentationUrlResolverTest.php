<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Support\Admin\DocumentationUrlResolver;
use WebBlocks\Cms\Tests\TestCase;

class DocumentationUrlResolverTest extends TestCase
{
  #[Test]
  public function it_resolves_every_supported_admin_locale_to_the_documentation_site(): void
  {
    config()->set('webblocks-cms.admin.documentation_url', 'https://cms.webblocksui.com/');

    $resolver = app(DocumentationUrlResolver::class);

    $this->assertSame('https://cms.webblocksui.com', $resolver->url('en'));
    $this->assertSame('https://cms.webblocksui.com/de', $resolver->url('de'));
    $this->assertSame('https://cms.webblocksui.com/tr', $resolver->url('tr'));
    $this->assertSame('https://cms.webblocksui.com/es', $resolver->url('es'));
    $this->assertSame('https://cms.webblocksui.com/it', $resolver->url('it'));
    $this->assertSame('https://cms.webblocksui.com/fr', $resolver->url('fr'));
  }

  #[Test]
  public function an_unknown_locale_falls_back_to_the_english_documentation_root(): void
  {
    config()->set('webblocks-cms.admin.documentation_url', 'https://docs.example.test');

    $this->assertSame(
      'https://docs.example.test',
      app(DocumentationUrlResolver::class)->url('unknown'),
    );
  }

  #[Test]
  public function an_invalid_configured_url_falls_back_to_the_official_site(): void
  {
    config()->set('webblocks-cms.admin.documentation_url', 'javascript:alert(1)');

    $this->assertSame(
      'https://cms.webblocksui.com/fr',
      app(DocumentationUrlResolver::class)->url('fr'),
    );
  }
}
