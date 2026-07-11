<?php

namespace WebBlocks\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlockType extends CmsModel
{
  use HasFactory;

  protected $fillable = [
    'name',
    'slug',
    'description',
    'category',
    'source_type',
    'is_system',
    'is_container',
    'sort_order',
    'status',
  ];

  protected function casts(): array
  {
    return [
      'is_system' => 'boolean',
      'is_container' => 'boolean',
    ];
  }

  public function blocks(): HasMany
  {
    return $this->hasMany(Block::class);
  }

  public function kindLabel(): string
  {
    return $this->is_system ? 'System Block' : 'Content Block';
  }
}
