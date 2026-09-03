<?php

namespace WebBlocks\Cms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsApiToken extends CmsModel
{
  protected $fillable = [
    'name',
    'token_hash',
    'token_preview',
    'capabilities',
    'token_type',
    'allowed_site_ids',
    'allowed_ip_ranges',
    'requests_per_minute',
    'expires_at',
    'created_by_user_id',
    'last_used_at',
    'last_used_ip',
    'last_used_user_agent',
    'revoked_at',
  ];

  protected function casts(): array
  {
    return [
      'last_used_at' => 'datetime',
      'capabilities' => 'array',
      'allowed_site_ids' => 'array',
      'allowed_ip_ranges' => 'array',
      'requests_per_minute' => 'integer',
      'expires_at' => 'datetime',
      'revoked_at' => 'datetime',
    ];
  }

  public function creator(): BelongsTo
  {
    return $this->belongsTo(User::class, 'created_by_user_id');
  }

  public function activityLogs(): HasMany
  {
    return $this->hasMany(CmsApiTokenActivityLog::class, 'cms_api_token_id');
  }

  public function scopeActive(Builder $query): Builder
  {
    return $query->whereNull('revoked_at');
  }

  public function isRevoked(): bool
  {
    return $this->revoked_at !== null;
  }

  public function isPersonal(): bool
  {
    return $this->token_type === 'personal';
  }

  public function isExpired(): bool
  {
    return $this->expires_at !== null && $this->expires_at->isPast();
  }

  public function statusLabel(): string
  {
    return match (true) {
      $this->isRevoked() => 'Revoked',
      $this->isExpired() => 'Expired',
      default => 'Active',
    };
  }

  public function statusBadgeClass(): string
  {
    return ($this->isRevoked() || $this->isExpired()) ? 'wb-status-pending' : 'wb-status-active';
  }

  public function createdAtLabel(): string
  {
    return $this->created_at?->format('Y-m-d H:i') ?? 'Unknown';
  }

  public function lastUsedAtLabel(): string
  {
    return $this->last_used_at?->format('Y-m-d H:i') ?? 'Not used yet';
  }
}
