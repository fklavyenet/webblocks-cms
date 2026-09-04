<?php

namespace WebBlocks\Cms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemUpdateRun extends CmsModel
{
  use HasFactory;

  public const STATUS_SUCCESS = 'success';

  public const STATUS_SUCCESS_WITH_WARNINGS = 'success_with_warnings';

  public const STATUS_FAILED = 'failed';

  public const STATUS_RESTORED = 'restored';

  // Historic-only statuses: no code writes these anymore, but retained rows
  // from the retired two-phase flow must keep rendering.
  public const STATUS_PENDING = 'pending';

  public const STATUS_RUNNING = 'running';

  public const STATUS_CANCELLED = 'cancelled';

  protected $fillable = [
    'from_version',
    'to_version',
    'status',
    'summary',
    'output',
    'warning_count',
    'started_at',
    'finished_at',
    'duration_ms',
    'triggered_by_user_id',
  ];

  protected $casts = [
    'started_at' => 'datetime',
    'finished_at' => 'datetime',
  ];

  public function triggeredBy(): BelongsTo
  {
    return $this->belongsTo(config('auth.providers.users.model', User::class), 'triggered_by_user_id');
  }

  public function statusLabel(): string
  {
    return match ($this->status) {
      self::STATUS_RESTORED => 'Failed, backup restored',
      default => str_replace('_', ' ', $this->status),
    };
  }

  public function statusBadgeClass(): string
  {
    return match ($this->status) {
      self::STATUS_SUCCESS => 'wb-status-active',
      self::STATUS_SUCCESS_WITH_WARNINGS => 'wb-status-pending',
      self::STATUS_PENDING => 'wb-status-pending',
      self::STATUS_RUNNING => 'wb-status-pending',
      self::STATUS_RESTORED => 'wb-status-danger',
      default => 'wb-status-danger',
    };
  }

  public function durationLabel(): string
  {
    if ($this->duration_ms === null) {
      return '-';
    }

    if ($this->duration_ms < 1000) {
      return number_format($this->duration_ms).' ms';
    }

    return number_format($this->duration_ms / 1000, 1).' s';
  }
}
