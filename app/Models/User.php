<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'is_admin', 'is_active', 'last_login_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_SITE_ADMIN = 'site_admin';

    public const ROLE_EDITOR = 'editor';

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            $user->role = $user->normalizedRole();
            $user->is_admin = $user->role === self::ROLE_SUPER_ADMIN;
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'role' => 'string',
            'is_admin' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public static function roles(): array
    {
        return [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_SITE_ADMIN,
            self::ROLE_EDITOR,
        ];
    }

    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class)
            ->withTimestamps();
    }

    public function scopeWithRoleOrder(Builder $query): Builder
    {
        return $query->orderByRaw("case role when ? then 0 when ? then 1 when ? then 2 else 3 end", [
            self::ROLE_SUPER_ADMIN,
            self::ROLE_SITE_ADMIN,
            self::ROLE_EDITOR,
        ]);
    }

    public function normalizedRole(): string
    {
        $role = is_string($this->role) ? trim($this->role) : '';

        if (in_array($role, self::roles(), true)) {
            return $role;
        }

        return $this->is_admin ? self::ROLE_SUPER_ADMIN : self::ROLE_EDITOR;
    }

    public function isSuperAdmin(): bool
    {
        return $this->normalizedRole() === self::ROLE_SUPER_ADMIN;
    }

    public function isSiteAdmin(): bool
    {
        return $this->normalizedRole() === self::ROLE_SITE_ADMIN;
    }

    public function isEditor(): bool
    {
        return $this->normalizedRole() === self::ROLE_EDITOR;
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
            return Site::query()->pluck('sites.id');
        }

        if ($this->relationLoaded('sites')) {
            return $this->sites->pluck('id')->values();
        }

        return $this->sites()->pluck('sites.id');
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
        return $this->is_active
            && ($this->isSuperAdmin() || $this->accessibleSiteIds()->isNotEmpty());
    }

    public function roleLabel(): string
    {
        return match ($this->normalizedRole()) {
            self::ROLE_SUPER_ADMIN => 'Super Admin',
            self::ROLE_SITE_ADMIN => 'Site Admin',
            default => 'Editor',
        };
    }

    public function roleBadgeClass(): string
    {
        return match ($this->normalizedRole()) {
            self::ROLE_SUPER_ADMIN => 'wb-status-info',
            self::ROLE_SITE_ADMIN => 'wb-status-active',
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
        return $this->is_active ? 'Active' : 'Inactive';
    }

    public function statusBadgeClass(): string
    {
        return $this->is_active ? 'wb-status-active' : 'wb-status-pending';
    }

    public function lastLoginLabel(): string
    {
        return $this->last_login_at?->format('Y-m-d H:i') ?? 'No login yet';
    }
}
