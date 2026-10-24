<?php

namespace WebBlocks\Cms\Support\Access;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Support\PublicRendering\RequestMemo;

final class CmsAuthorization
{
  public function ready(): bool
  {
    return $this->remember('schema', [], function (): bool {
      $schema = DB::connection()->getSchemaBuilder();

      return $schema->hasTable('wbcms_acl_roles') && $schema->hasColumn('wbcms_site_user', 'role_id');
    });
  }

  public function isSuperAdmin($user): bool
  {
    if (! $user) {
    return false;
    }
    if ($this->ready()) {
      return $user->getAuthIdentifier() !== null && $this->remember('system-administrator', [$user->getAuthIdentifier()],
        fn () => DB::table('wbcms_acl_system_administrators')->where('user_id', $user->getAuthIdentifier())->exists());
    }

    return ($user->role ?? null) === 'super_admin' || (bool) ($user->is_admin ?? false);
  }

  public function siteIds($user, string $permission = 'cms.panel.access'): Collection
  {
    if (! $user || ! ($user->is_active ?? true)) {
    return collect();
    }
    if ($this->isSuperAdmin($user)) {
    return clone $this->remember('all-sites', [], fn () => DB::table('wbcms_sites')->pluck('id'));
    }
    if (! $this->ready()) {
    return $user->sites()->pluck('wbcms_sites.id');
    }

    $query = DB::table('wbcms_site_user as membership')
      ->join('wbcms_acl_role_permissions as grant', 'grant.role_id', '=', 'membership.role_id')
      ->where('membership.user_id', $user->getAuthIdentifier())->where('grant.permission', $permission);
    if ($permission !== 'cms.panel.access') {
      $query->whereExists(fn ($q) => $q->selectRaw('1')->from('wbcms_acl_role_permissions as panel_grant')->whereColumn('panel_grant.role_id', 'membership.role_id')->where('panel_grant.permission', 'cms.panel.access'));
    }

    return clone $this->remember('permission-sites', [$user->getAuthIdentifier(), $permission],
      fn () => $query->pluck('membership.site_id')->map(fn ($id) => (int) $id));
  }

  public function allows($user, string $permission, int|string|null $siteId = null): bool
  {
    if (! $user || ! ($user->is_active ?? true) || ! app(PermissionCatalog::class)->known($permission)) {
    return false;
    }
    if ($this->isSuperAdmin($user)) {
    return true;
    }
    if (! $this->ready()) {
      return in_array($user->role ?? null, ['site_admin', 'editor'], true) && in_array($permission, app(PermissionCatalog::class)->defaults($user->role), true)
        && ($siteId === null || $user->hasSiteAccess($siteId));
    }
    $sites = $this->siteIds($user, $permission)->intersect($this->siteIds($user));

    return $siteId === null ? $sites->isNotEmpty() : $sites->contains((int) $siteId);
  }

  public function usesLegacyRoles($user): bool
  {
    if (! $this->ready() || ! $user) {
    return false;
    }
    $memberships = DB::table('wbcms_site_user as m')->leftJoin('wbcms_acl_roles as r', 'r.id', '=', 'm.role_id')->where('m.user_id', $user->getAuthIdentifier());

    return $this->remember('legacy-memberships', [$user->getAuthIdentifier()],
      fn () => (clone $memberships)->exists() && ! (clone $memberships)->where(fn ($q) => $q->whereNull('r.id')->orWhere('r.is_protected', false))->exists());
  }

  private function remember(string $group, array $identity, callable $load): mixed
  {
    $connection = DB::connection();

    return app(RequestMemo::class)->remember('acl.'.$group, [
      $connection->getName(), $connection->getDatabaseName(), $connection->getTablePrefix(), ...$identity,
    ], $load);
  }

  public function legacyApiSiteIds($user): Collection
  {
    return DB::table('wbcms_site_user as m')->join('wbcms_acl_roles as r', 'r.id', '=', 'm.role_id')
      ->where('m.user_id', $user->getAuthIdentifier())->where('r.is_protected', true)->pluck('m.site_id');
  }

  public function legacyApiAllows($user, string $permission, int $siteId): bool
  {
    foreach (app(PermissionCatalog::class)->capabilityPermissions() as $capability => $mapped) {
      if ($mapped === $permission && $this->allows($user, 'cms.legacy-api.'.$capability, $siteId)) {
      return true;
      }
    }

    return false;
  }

  public function roleId($user, int $siteId): ?int
  {
    $id = DB::table('wbcms_site_user')->where('user_id', $user->getAuthIdentifier())->where('site_id', $siteId)->value('role_id');

    return $id === null ? null : (int) $id;
  }

  public function legacyRole($user, int $siteId): ?string
  {
    return DB::table('wbcms_site_user as m')->join('wbcms_acl_roles as r', 'r.id', '=', 'm.role_id')
      ->where('m.user_id', $user->getAuthIdentifier())->where('m.site_id', $siteId)->where('r.is_protected', true)->value('r.handle');
  }
}
