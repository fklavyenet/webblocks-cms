<?php

namespace WebBlocks\Cms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsApiToken extends Model
{
  protected $fillable = [
    'name',
    'token_hash',
    'token_preview',
    'capabilities',
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

  public function statusLabel(): string
  {
    return $this->isRevoked() ? 'Revoked' : 'Active';
  }

  public function statusBadgeClass(): string
  {
    return $this->isRevoked() ? 'wb-status-pending' : 'wb-status-active';
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
