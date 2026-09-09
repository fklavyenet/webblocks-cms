<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Tests\TestCase;

class AdminRoleAccessMatrixTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);

    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  public static function roleMatrix(): array
  {
    return [
      'super admin' => ['super_admin', true, true, true],
      'site admin' => ['site_admin', false, true, true],
      'editor' => ['editor', false, false, false],
    ];
  }

  #[Test]
  #[DataProvider('roleMatrix')]
  public function core_admin_abilities_follow_the_role_matrix(
    string $role,
    bool $accessSystem,
    bool $manageSiteOperations,
    bool $viewVisitorReports,
  ): void {
    $user = $this->user($role);

    $this->assertSame($accessSystem, Gate::forUser($user)->allows('access-system'));
    $this->assertSame($manageSiteOperations, Gate::forUser($user)->allows('manage-site-operations'));
    $this->assertSame($viewVisitorReports, Gate::forUser($user)->allows('view-visitor-reports'));
  }

  #[Test]
  public function operational_routes_enforce_the_same_abilities_used_by_navigation(): void
  {
    $expected = [
      'admin.reports.visitors.index' => 'can:view-visitor-reports',
      'admin.contact-messages.index' => 'can:manage-site-operations',
      'admin.contact-messages.show' => 'can:manage-site-operations',
      'admin.engagement.index' => 'can:manage-site-operations',
      'admin.engagement.comments.index' => 'can:manage-site-operations',
      'admin.engagement.ratings.index' => 'can:manage-site-operations',
    ];

    foreach ($expected as $routeName => $middleware) {
      $route = app('router')->getRoutes()->getByName($routeName);

      $this->assertNotNull($route, $routeName);
      $this->assertContains($middleware, $route->gatherMiddleware(), $routeName);
    }
  }

  #[Test]
  public function site_index_is_site_scoped_but_site_creation_remains_system_only(): void
  {
    $index = app('router')->getRoutes()->getByName('admin.sites.index');
    $create = app('router')->getRoutes()->getByName('admin.sites.create');

    $this->assertNotNull($index);
    $this->assertNotContains('can:access-system', $index->gatherMiddleware());
    $this->assertContains('can:access-system', $create->gatherMiddleware());
  }

  private function user(string $role): AuthenticatableUser
  {
    $user = new AuthenticatableUser;
    $user->role = $role;
    $user->is_admin = $role === 'super_admin';

    return $user;
  }
}
