<?php

namespace WebBlocks\Cms\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CmsApiTokenActivityLog extends Model
{
  protected $fillable = [
    'cms_api_token_id',
    'occurred_at',
    'status',
    'method',
    'path',
    'route_name',
    'required_capability',
    'ip',
    'user_agent',
  ];

  protected function casts(): array
  {
    return [
      'occurred_at' => 'datetime',
    ];
  }

  public function token(): BelongsTo
  {
    return $this->belongsTo(CmsApiToken::class, 'cms_api_token_id');
  }

  public function occurredAtLabel(): string
  {
    return $this->occurred_at?->format('Y-m-d H:i:s') ?? 'Unknown';
  }

  public function statusLabel(): string
  {
    return match ($this->status) {
      'allowed' => 'Allowed',
      'denied' => 'Denied',
      default => 'Authenticated',
    };
  }

  public function statusBadgeClass(): string
  {
    return match ($this->status) {
      'allowed' => 'wb-status-active',
      'denied' => 'wb-status-pending',
      default => 'wb-status-info',
    };
  }
}
