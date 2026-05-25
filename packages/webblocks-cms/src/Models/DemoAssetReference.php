<?php

namespace WebBlocks\Cms\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemoAssetReference extends Model
{
  use HasFactory;

  protected $table = 'demo_media_references';

  protected $fillable = [
    'source_key',
    'media_id',
    'asset_id',
  ];

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

  public function media(): BelongsTo
  {
    return $this->belongsTo(Media::class, 'media_id');
  }

  public function asset(): BelongsTo
  {
    return $this->media();
  }
}
