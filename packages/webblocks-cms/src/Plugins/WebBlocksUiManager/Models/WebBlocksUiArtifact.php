<?php

namespace WebBlocks\Cms\Plugins\WebBlocksUiManager\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebBlocksUiArtifact extends Model
{
  public const STATUS_TRACKED = 'tracked';

  public const STATUS_MISSING = 'missing';

  protected $table = 'webblocks_ui_manager_artifacts';

  protected $fillable = [
    'release_id',
    'handle',
    'source_path',
    'target_path',
    'public_url',
    'checksum_sha256',
    'size_bytes',
    'mime_type',
    'metadata',
    'status',
  ];

  protected $casts = [
    'metadata' => 'array',
  ];

  public function release(): BelongsTo
  {
    return $this->belongsTo(WebBlocksUiRelease::class, 'release_id');
  }

  public function humanSize(): string
  {
    if ($this->size_bytes === null) {
      return '-';
    }

    if ($this->size_bytes < 1024) {
      return number_format($this->size_bytes).' B';
    }

    if ($this->size_bytes < 1024 * 1024) {
      return number_format($this->size_bytes / 1024, 1).' KB';
    }

    return number_format($this->size_bytes / (1024 * 1024), 1).' MB';
  }
}
