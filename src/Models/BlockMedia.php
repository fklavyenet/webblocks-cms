<?php

namespace WebBlocks\Cms\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use WebBlocks\Cms\Models\Concerns\HasRevisionIdentity;
use WebBlocks\Cms\Models\Concerns\ReusesMediaRelationAliases;
use WebBlocks\Cms\Support\Media\GalleryLayout;

class BlockMedia extends CmsModel
{
  use HasFactory;
  use HasRevisionIdentity;
  use ReusesMediaRelationAliases;

  protected $table = 'block_media';

  protected $fillable = [
    'block_id',
    'media_id',
    'asset_id',
    'role',
    'position',
    'physical_width_um',
    'physical_height_um',
  ];

  public function galleryDimensions(): array
  {
    return $this->role === 'gallery_item'
      ? array_filter(GalleryLayout::dimensions($this->getAttributes())) : [];
  }

  protected function mediaId(): Attribute
  {
    return Attribute::make(
      get: fn ($value, array $attributes) => $value ?? ($attributes['asset_id'] ?? null),
      set: fn ($value) => ['media_id' => $value],
    );
  }

  protected function assetId(): Attribute
  {
    return Attribute::make(
      get: fn ($value, array $attributes) => $value ?? ($attributes['media_id'] ?? null),
      set: fn ($value) => ['media_id' => $value],
    );
  }

  public function block(): BelongsTo
  {
    return $this->belongsTo(Block::class);
  }

  public function media(): BelongsTo
  {
    return $this->belongsTo(Media::class, 'media_id');
  }

  public function asset(): BelongsTo
  {
    return $this->media();
  }

  public function galleryItemTranslations(): HasMany
  {
    return $this->hasMany(BlockGalleryItemTranslation::class, 'block_media_id');
  }
}
