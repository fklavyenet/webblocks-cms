<?php

namespace WebBlocks\Cms\Tests\Unit;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use WebBlocks\Cms\Support\PublicDemo\PublicDemoGuard;
use WebBlocks\Cms\Tests\TestCase;

class PublicDemoGuardTest extends TestCase
{
  public function test_demo_mode_requires_all_environment_and_host_guards(): void
  {
    config()->set('webblocks-cms.public_demo.enabled', true);
    config()->set('webblocks-cms.public_demo.environment', 'testing');
    config()->set('webblocks-cms.public_demo.host', 'demo.example.test');

    $guard = app(PublicDemoGuard::class);

    $this->assertTrue($guard->isConfiguredFor(Request::create('https://demo.example.test/webadmin')));
    $this->assertFalse($guard->isConfiguredFor(Request::create('https://www.example.test/webadmin')));

    config()->set('webblocks-cms.public_demo.host', null);
    $this->assertFalse($guard->isConfiguredFor(Request::create('https://demo.example.test/webadmin')));
  }

  public function test_only_the_configured_active_editor_is_the_demo_identity(): void
  {
    config()->set('webblocks-cms.public_demo.user_email', 'demo@example.test');
    $guard = app(PublicDemoGuard::class);

    $this->assertTrue($guard->isDemoUser(new PublicDemoTestUser([
      'email' => 'DEMO@example.test',
      'role' => 'editor',
      'is_active' => true,
    ])));
    $this->assertFalse($guard->isDemoUser(new PublicDemoTestUser([
      'email' => 'demo@example.test',
      'role' => 'site_admin',
      'is_active' => true,
    ])));
    $this->assertFalse($guard->isDemoUser(new PublicDemoTestUser([
      'email' => 'someone@example.test',
      'role' => 'editor',
      'is_active' => true,
    ])));
  }

  public function test_reads_use_a_denylist_and_writes_use_a_closed_allowlist(): void
  {
    config()->set('webblocks-cms.public_demo.denied_read_routes', ['admin.profile.*']);
    config()->set('webblocks-cms.public_demo.allowed_write_routes', ['admin.pages.update']);
    $guard = app(PublicDemoGuard::class);

    $this->assertTrue($guard->permits($this->namedRequest('GET', 'admin.pages.index')));
    $this->assertFalse($guard->permits($this->namedRequest('GET', 'admin.profile.api-tokens.index')));
    $this->assertTrue($guard->permits($this->namedRequest('PUT', 'admin.pages.update')));
    $this->assertFalse($guard->permits($this->namedRequest('POST', 'admin.media.store')));
    $this->assertFalse($guard->permits($this->namedRequest('POST', null)));
  }

  private function namedRequest(string $method, ?string $name): Request
  {
    $request = Request::create('/webadmin/example', $method);
    $route = new Route($method, '/webadmin/example', fn () => null);

    if ($name !== null) {
      $route->name($name);
    }

    $request->setRouteResolver(fn () => $route);

    return $request;
  }
}

class PublicDemoTestUser extends GenericUser
{
  public function normalizedRole(): string
  {
    return (string) $this->role;
  }
}
