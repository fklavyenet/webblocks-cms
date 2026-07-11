<?php

namespace WebBlocks\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaFolder extends CmsModel
{
  use HasFactory;

  protected $table = 'media_folders';

  protected $fillable = [
    'parent_id',
    'name',
    'slug',
  ];

  public function parent(): BelongsTo
  {
    return $this->belongsTo(self::class, 'parent_id');
  }

  public function children(): HasMany
  {
    return $this->hasMany(self::class, 'parent_id')->orderBy('name');
  }

  public function media(): HasMany
  {
    return $this->hasMany(Media::class, 'folder_id');
  }

  public function assets(): HasMany
  {
    return $this->media();
  }
}
