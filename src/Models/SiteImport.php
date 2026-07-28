<?php

namespace WebBlocks\Cms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteImport extends CmsModel
{
  use HasFactory;

  public const STATUS_RUNNING = 'running';

  /**
   * Started, committed some of its work, and not finished.
   *
   * Chunked importing trades all-or-nothing for a run that survives a dropped
   * request. The price is this state: real rows exist for a site that is not
   * complete. It stays unaddressable because the domain phase runs last.
   */
  public const STATUS_PARTIAL = 'partial';

  public const STATUS_VALIDATED = 'validated';

  public const STATUS_COMPLETED = 'completed';

  public const STATUS_FAILED = 'failed';

  protected $fillable = [
    'user_id',
    'status',
    'source_archive_name',
    'archive_disk',
    'archive_path',
    'target_site_id',
    'imported_site_handle',
    'imported_site_domain',
    'summary_json',
    'manifest_json',
    'output_log',
    'failure_message',
    'resume_phase',
    'resume_offset',
    'resume_state',
    'progress_done',
    'progress_total',
    'heartbeat_at',
  ];

  protected function casts(): array
  {
    return [
      'summary_json' => 'array',
      'manifest_json' => 'array',
      'resume_state' => 'array',
      'resume_offset' => 'integer',
      'progress_done' => 'integer',
      'progress_total' => 'integer',
      'heartbeat_at' => 'datetime',
    ];
  }

  public function user(): BelongsTo
  {
    return $this->belongsTo(User::class);
  }

  public function targetSite(): BelongsTo
  {
    return $this->belongsTo(Site::class, 'target_site_id');
  }

  public function isValidated(): bool
  {
    return $this->status === self::STATUS_VALIDATED;
  }

  public function isCompleted(): bool
  {
    return $this->status === self::STATUS_COMPLETED;
  }

  public function isFailed(): bool
  {
    return $this->status === self::STATUS_FAILED;
  }

  public function isPartial(): bool
  {
    return $this->status === self::STATUS_PARTIAL;
  }

  /**
   * Has work left that a step can pick up.
   *
   * A failed run is resumable too: the failure is recorded against the phase
   * that raised it, and the committed phases before it stay done.
   */
  public function isResumable(): bool
  {
    return in_array($this->status, [self::STATUS_PARTIAL, self::STATUS_FAILED], true)
      && $this->resume_phase !== null;
  }

  public function progressPercent(): int
  {
    if ($this->progress_total < 1) {
      return $this->isCompleted() ? 100 : 0;
    }

    return (int) min(100, floor(($this->progress_done / $this->progress_total) * 100));
  }

  public function statusLabel(): string
  {
    return str($this->status)->replace('_', ' ')->title()->toString();
  }

  public function statusBadgeClass(): string
  {
    return match ($this->status) {
      self::STATUS_COMPLETED => 'wb-status-active',
      self::STATUS_VALIDATED, self::STATUS_RUNNING, self::STATUS_PARTIAL => 'wb-status-pending',
      default => 'wb-status-danger',
    };
  }
}
