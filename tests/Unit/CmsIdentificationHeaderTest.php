<?php

namespace WebBlocks\Cms\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use WebBlocks\Cms\Http\Middleware\AddCmsIdentificationHeader;
use WebBlocks\Cms\Tests\TestCase;

class CmsIdentificationHeaderTest extends TestCase
{
  public function test_it_adds_a_versionless_cms_header_by_default(): void
  {
    $response = app(AddCmsIdentificationHeader::class)->handle(
      Request::create('/'),
      fn () => new Response('ok')
    );

    $this->assertSame('WebBlocks CMS', $response->headers->get('X-Powered-By'));
    $this->assertStringNotContainsString('1.', (string) $response->headers->get('X-Powered-By'));
  }

  public function test_the_header_can_be_disabled(): void
  {
    config()->set('webblocks-cms.public.send_powered_by_header', false);

    $response = app(AddCmsIdentificationHeader::class)->handle(
      Request::create('/'),
      fn () => new Response('ok')
    );

    $this->assertFalse($response->headers->has('X-Powered-By'));
  }

  public function test_public_page_routes_use_the_identification_middleware(): void
  {
    foreach (['home', 'localized.home', 'search', 'localized.search', 'pages.show', 'localized.pages.show'] as $name) {
      $this->assertContains(
        AddCmsIdentificationHeader::class,
        app('router')->getRoutes()->getByName($name)?->gatherMiddleware() ?? [],
        "The {$name} route does not publish the CMS identification header."
      );
    }

    $this->assertNotContains(
      AddCmsIdentificationHeader::class,
      app('router')->getRoutes()->getByName('contact-messages.store')?->gatherMiddleware() ?? []
    );
  }
}
