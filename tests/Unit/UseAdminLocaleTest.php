<?php

namespace WebBlocks\Cms\Tests\Unit;

use Illuminate\Http\Request;
use Mockery;
use WebBlocks\Cms\Http\Middleware\UseAdminLocale;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Tests\TestCase;

class UseAdminLocaleTest extends TestCase
{
  public function test_it_applies_the_selected_admin_locale_to_standard_translations(): void
  {
    $resolver = Mockery::mock(AdminLocaleResolver::class);
    $resolver->shouldReceive('locale')->once()->andReturn('de');

    $middleware = new UseAdminLocale($resolver);
    $request = Request::create('/webadmin/plugins/example', 'GET');

    $response = $middleware->handle($request, fn () => response('ok'));

    $this->assertSame('ok', $response->getContent());
    $this->assertSame('de', app()->getLocale());
  }
}
