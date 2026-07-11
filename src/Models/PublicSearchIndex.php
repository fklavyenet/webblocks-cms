<?php

namespace WebBlocks\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicSearchIndex extends CmsModel
{
  use HasFactory;

  protected $table = 'public_search_index';

  protected $fillable = [
    'site_id',
    'locale_id',
    'page_id',
    'title',
    'excerpt',
    'url',
    'content',
    'indexed_at',
  ];

  protected function casts(): array
  {
    return [
      'indexed_at' => 'datetime',
    ];
  }

  public function site(): BelongsTo
  {
    return $this->belongsTo(Site::class);
  }

  public function locale(): BelongsTo
  {
    return $this->belongsTo(Locale::class);
  }

  public function page(): BelongsTo
  {
    return $this->belongsTo(Page::class);
  }
}
