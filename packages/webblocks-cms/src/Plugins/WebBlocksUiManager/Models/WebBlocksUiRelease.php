<?php

namespace WebBlocks\Cms\Plugins\WebBlocksUiManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebBlocksUiRelease extends Model
{
  public const STATUS_DRAFT = 'draft';

  public const STATUS_PREPARED = 'prepared';

  public const STATUS_PUBLISHED = 'published';

  public const STATUS_PUBLISH_FAILED = 'publish_failed';

  public const STATUS_BLOCKED = 'blocked';

  protected $table = 'webblocks_ui_manager_releases';

  protected $fillable = [
    'version',
    'label',
    'status',
    'notes',
    'cdn_base_path',
    'cdn_base_url',
    'manifest_path',
    'manifest',
    'prepared_at',
    'published_at',
  ];

  protected $casts = [
    'manifest' => 'array',
    'prepared_at' => 'datetime',
    'published_at' => 'datetime',
  ];

  public function artifacts(): HasMany
  {
    return $this->hasMany(WebBlocksUiArtifact::class, 'release_id')->orderBy('handle');
  }

  public function publishRuns(): HasMany
  {
    return $this->hasMany(WebBlocksUiPublishRun::class, 'release_id')->latest('id');
  }

  public function statusLabel(): string
  {
    return str_replace('_', ' ', $this->status ?: self::STATUS_DRAFT);
  }

  public function statusBadgeClass(): string
  {
    return match ($this->status) {
      self::STATUS_PREPARED => 'wb-status-info',
      self::STATUS_PUBLISHED => 'wb-status-active',
      self::STATUS_PUBLISH_FAILED => 'wb-status-danger',
      self::STATUS_BLOCKED => 'wb-status-warning',
      default => 'wb-status-pending',
    };
  }
}
