<?php

namespace WebBlocks\Cms\Support\PublicDemo;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PublicDemoGuard
{
  public function isConfiguredFor(Request $request): bool
  {
    $expectedHost = trim((string) config('webblocks-cms.public_demo.host'));
    $expectedEnvironment = trim((string) config('webblocks-cms.public_demo.environment', 'demo'));

    return (bool) config('webblocks-cms.public_demo.enabled', false)
      && $expectedHost !== ''
      && $expectedEnvironment !== ''
      && app()->environment($expectedEnvironment)
      && hash_equals(Str::lower($expectedHost), Str::lower($request->getHost()));
  }

  public function isDemoUser(?Authenticatable $user): bool
  {
    if (! $user) {
      return false;
    }

    $expectedEmail = Str::lower(trim((string) config('webblocks-cms.public_demo.user_email')));
    $actualEmail = Str::lower(trim((string) ($user->email ?? '')));
    $role = method_exists($user, 'normalizedRole') ? $user->normalizedRole() : (string) ($user->role ?? '');

    return $expectedEmail !== ''
      && $actualEmail !== ''
      && hash_equals($expectedEmail, $actualEmail)
      && $role === 'editor'
      && (bool) ($user->is_active ?? true);
  }

  public function permits(Request $request): bool
  {
    $routeName = (string) $request->route()?->getName();

    if ($request->isMethodSafe()) {
      return ! $this->matches($routeName, config('webblocks-cms.public_demo.denied_read_routes', []));
    }

    return $this->matches($routeName, config('webblocks-cms.public_demo.allowed_write_routes', []));
  }

  private function matches(string $routeName, mixed $patterns): bool
  {
    if ($routeName === '' || ! is_array($patterns)) {
      return false;
    }

    return collect($patterns)
      ->filter(fn (mixed $pattern): bool => is_string($pattern) && $pattern !== '')
      ->contains(fn (string $pattern): bool => Str::is($pattern, $routeName));
  }
}
