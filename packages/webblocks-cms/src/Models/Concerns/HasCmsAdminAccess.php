<?php

namespace WebBlocks\Cms\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Database\CmsTable;

trait HasCmsAdminAccess
{
  public const ROLE_SUPER_ADMIN = 'super_admin';

  public const ROLE_SITE_ADMIN = 'site_admin';

  public const ROLE_EDITOR = 'editor';

  public function initializeHasCmsAdminAccess(): void
  {
    $this->mergeFillable(['role', 'is_admin', 'is_active', 'last_login_at']);
    $this->mergeCasts([
      'role' => 'string',
      'is_admin' => 'boolean',
      'is_active' => 'boolean',
      'last_login_at' => 'datetime',
    ]);
  }

  public static function bootHasCmsAdminAccess(): void
  {
    static::saving(function ($user): void {
      $role = $user->normalizedRole();

      $user->role = $role !== '' ? $role : null;
      $user->is_admin = $role === static::ROLE_SUPER_ADMIN;

      if ($role !== '') {
        $user->is_active = $user->is_active ?? true;
      }
    });
  }

  public static function roles(): array
  {
    return [
      static::ROLE_SUPER_ADMIN,
      static::ROLE_SITE_ADMIN,
      static::ROLE_EDITOR,
    ];
  }

  public function sites(): BelongsToMany
  {
    return $this->belongsToMany(Site::class, CmsTable::name('site_user'))
      ->withTimestamps();
  }

  public function scopeWithRoleOrder(Builder $query): Builder
  {
    return $query->orderByRaw('case role when ? then 0 when ? then 1 when ? then 2 else 3 end', [
      static::ROLE_SUPER_ADMIN,
      static::ROLE_SITE_ADMIN,
      static::ROLE_EDITOR,
    ]);
  }

  public function scopeCmsUsers(Builder $query): Builder
  {
    return $query->where(function (Builder $subquery): void {
      $subquery
        ->where('role', static::ROLE_SUPER_ADMIN)
        ->orWhereHas('sites');
    });
  }

  public function normalizedRole(): string
  {
    $role = is_string($this->role ?? null) ? trim((string) $this->role) : '';

    if (in_array($role, static::roles(), true)) {
      return $role;
    }

    return (bool) ($this->is_admin ?? false) ? static::ROLE_SUPER_ADMIN : '';
  }

  public function isSuperAdmin(): bool
  {
    return $this->normalizedRole() === static::ROLE_SUPER_ADMIN;
  }

  public function isSiteAdmin(): bool
  {
    return $this->normalizedRole() === static::ROLE_SITE_ADMIN;
  }

  public function isEditor(): bool
  {
    return $this->normalizedRole() === static::ROLE_EDITOR;
  }

  public function requiresSiteAssignments(): bool
  {
    return ! $this->isSuperAdmin();
  }

  public function accessibleSites()
  {
    if ($this->isSuperAdmin()) {
      return Site::query()->primaryFirst()->orderBy('name')->get();
    }

    if ($this->relationLoaded('sites')) {
      return $this->sites
        ->sortByDesc('is_primary')
        ->sortBy('name')
        ->values();
    }

    return $this->sites()->primaryFirst()->orderBy('name')->get();
  }

  public function accessibleSiteIds()
  {
    if ($this->isSuperAdmin()) {
      return Site::query()->pluck((new Site)->qualifyColumn('id'));
    }

    if ($this->relationLoaded('sites')) {
      return $this->sites->pluck('id')->values();
    }

    return $this->sites()->pluck((new Site)->qualifyColumn('id'));
  }

  public function hasSiteAccess(Site|int|string|null $site): bool
  {
    if ($this->isSuperAdmin()) {
      return true;
    }

    $siteId = match (true) {
      $site instanceof Site => $site->id,
      is_numeric($site) => (int) $site,
      default => null,
    };

    if (! $siteId) {
      return false;
    }

    return $this->accessibleSiteIds()->contains($siteId);
  }

  public function canAccessAdmin(): bool
  {
    return (bool) ($this->is_active ?? true)
      && ($this->isSuperAdmin() || (($this->isSiteAdmin() || $this->isEditor()) && $this->accessibleSiteIds()->isNotEmpty()));
  }

  public function roleLabel(): string
  {
    return match ($this->normalizedRole()) {
      static::ROLE_SUPER_ADMIN => 'Super Admin',
      static::ROLE_SITE_ADMIN => 'Site Admin',
      default => 'Editor',
    };
  }

  public function roleBadgeClass(): string
  {
    return match ($this->normalizedRole()) {
      static::ROLE_SUPER_ADMIN => 'wb-status-info',
      static::ROLE_SITE_ADMIN => 'wb-status-active',
      default => 'wb-status-pending',
    };
  }

  public function siteAccessSummary(): string
  {
    if ($this->isSuperAdmin()) {
      return 'All sites';
    }

    $sites = $this->accessibleSites();

    if ($sites->isEmpty()) {
      return 'No assigned sites';
    }

    if ($sites->count() <= 2) {
      return $sites->pluck('name')->implode(', ');
    }

    return $sites->take(2)->pluck('name')->implode(', ').' +'.($sites->count() - 2);
  }

  public function statusLabel(): string
  {
    return ($this->is_active ?? true) ? 'Active' : 'Inactive';
  }

  public function statusBadgeClass(): string
  {
    return ($this->is_active ?? true) ? 'wb-status-active' : 'wb-status-pending';
  }

  public function lastLoginLabel(): string
  {
    return $this->last_login_at?->format('Y-m-d H:i') ?? 'No login yet';
  }
}
