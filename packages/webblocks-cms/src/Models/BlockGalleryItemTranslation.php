<?php

namespace WebBlocks\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BlockGalleryItemTranslation extends Model
{
  use HasFactory;

  protected $fillable = [
    'block_media_id',
    'locale_id',
    'alt_text',
    'caption',
    'overlay_title',
    'overlay_text',
  ];

  public function blockMedia(): BelongsTo
  {
    return $this->belongsTo(BlockMedia::class, 'block_media_id');
  }

  public function locale(): BelongsTo
  {
    return $this->belongsTo(Locale::class);
  }
}
