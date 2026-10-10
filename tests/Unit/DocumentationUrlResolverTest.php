<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Support\Admin\DocumentationUrlResolver;
use WebBlocks\Cms\Tests\TestCase;

class DocumentationUrlResolverTest extends TestCase
{
  #[Test]
  public function help_links_to_documentation_without_appending_the_admin_locale(): void
  {
    config()->set('webblocks-cms.admin.documentation_url', 'https://cms.webblocksui.com/');

    $resolver = app(DocumentationUrlResolver::class);

    $this->assertSame('https://cms.webblocksui.com/docs', $resolver->url('en'));
    $this->assertSame('https://cms.webblocksui.com/docs', $resolver->url('de'));
    $this->assertSame('https://cms.webblocksui.com/docs', $resolver->url('tr'));
    $this->assertSame('https://cms.webblocksui.com/docs', $resolver->url('es'));
    $this->assertSame('https://cms.webblocksui.com/docs', $resolver->url('it'));
    $this->assertSame('https://cms.webblocksui.com/docs', $resolver->url('fr'));
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
      'https://cms.webblocksui.com/docs',
      app(DocumentationUrlResolver::class)->url('fr'),
    );
  }
}
