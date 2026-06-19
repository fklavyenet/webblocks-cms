<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;
use WebBlocks\Cms\Http\Controllers\Auth\LoginController as CmsLoginController;
use WebBlocks\Cms\Http\Controllers\Auth\NewPasswordController as CmsNewPasswordController;
use WebBlocks\Cms\Http\Controllers\Auth\PasswordResetLinkController as CmsPasswordResetLinkController;

class HostAuthCompatibilityTest extends TestCase
{
  public function test_root_auth_compatibility_routes_remain_host_owned_when_available(): void
  {
    $this->assertRouteUses('password.request', PasswordResetLinkController::class.'@create');
    $this->assertRouteUses('password.reset', NewPasswordController::class.'@create');
    $this->assertRouteUses('register', RegisteredUserController::class.'@create');
    $this->assertRouteUses('verification.notice', EmailVerificationPromptController::class);
    $this->assertRouteUses('verification.verify', VerifyEmailController::class);
    $this->assertRouteUses('password.confirm', ConfirmablePasswordController::class.'@show');

    $this->assertSame('forgot-password', Route::getRoutes()->getByName('password.request')?->uri());
    $this->assertSame('reset-password/{token}', Route::getRoutes()->getByName('password.reset')?->uri());
    $this->assertSame('register', Route::getRoutes()->getByName('register')?->uri());
    $this->assertSame('verify-email', Route::getRoutes()->getByName('verification.notice')?->uri());
  }

  public function test_cms_package_auth_routes_use_webadmin_namespace_and_do_not_add_register(): void
  {
    $this->assertRouteUses('webblocks.auth.login', CmsLoginController::class.'@create');
    $this->assertRouteUses('webblocks.auth.logout', CmsLoginController::class.'@destroy');
    $this->assertRouteUses('webblocks.auth.password.request', CmsPasswordResetLinkController::class.'@create');
    $this->assertRouteUses('webblocks.auth.password.reset', CmsNewPasswordController::class.'@create');

    $this->assertSame('webadmin/login', Route::getRoutes()->getByName('webblocks.auth.login')?->uri());
    $this->assertSame('webadmin/logout', Route::getRoutes()->getByName('webblocks.auth.logout')?->uri());
    $this->assertSame('webadmin/forgot-password', Route::getRoutes()->getByName('webblocks.auth.password.request')?->uri());
    $this->assertSame('webadmin/reset-password/{token}', Route::getRoutes()->getByName('webblocks.auth.password.reset')?->uri());

    foreach (Route::getRoutes() as $route) {
      $this->assertNotSame('webadmin/register', $route->uri());
    }
  }

  private function assertRouteUses(string $routeName, string $expectedAction): void
  {
    $route = Route::getRoutes()->getByName($routeName);

    $this->assertNotNull($route, 'Route '.$routeName.' should be registered.');
    $this->assertSame($expectedAction, $route->getActionName());
  }
}
