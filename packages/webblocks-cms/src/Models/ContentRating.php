<?php

namespace WebBlocks\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentRating extends Model
{
  use HasFactory;

  protected $fillable = [
    'site_id',
    'page_id',
    'block_id',
    'rating_value',
    'rating_max',
    'status',
    'source_url',
    'visitor_hash',
    'ip_hash',
    'user_agent',
  ];

  protected function casts(): array
  {
    return [
      'rating_value' => 'integer',
      'rating_max' => 'integer',
    ];
  }

  public function site(): BelongsTo
  {
    return $this->belongsTo(Site::class);
  }

  public function page(): BelongsTo
  {
    return $this->belongsTo(Page::class)->with('translations');
  }

  public function block(): BelongsTo
  {
    return $this->belongsTo(Block::class);
  }
}
