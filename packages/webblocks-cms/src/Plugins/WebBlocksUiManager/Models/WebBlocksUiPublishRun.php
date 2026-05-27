<?php

namespace WebBlocks\Cms\Plugins\WebBlocksUiManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebBlocksUiPublishRun extends Model
{
  public const MODE_DRY_RUN = 'dry-run';

  public const MODE_PUBLISH = 'publish';

  public const STATUS_SUCCEEDED = 'succeeded';

  public const STATUS_BLOCKED = 'blocked';

  public const STATUS_FAILED = 'failed';

  protected $table = 'webblocks_ui_manager_publish_runs';

  protected $fillable = [
    'release_id',
    'mode',
    'status',
    'target_root',
    'target_release_path',
    'operations',
    'message',
    'started_at',
    'finished_at',
  ];

  protected $casts = [
    'operations' => 'array',
    'started_at' => 'datetime',
    'finished_at' => 'datetime',
  ];

  public function release(): BelongsTo
  {
    return $this->belongsTo(WebBlocksUiRelease::class, 'release_id');
  }

  public function statusBadgeClass(): string
  {
    return match ($this->status) {
      self::STATUS_SUCCEEDED => 'wb-status-active',
      self::STATUS_BLOCKED => 'wb-status-warning',
      self::STATUS_FAILED => 'wb-status-danger',
      default => 'wb-status-pending',
    };
  }
}
