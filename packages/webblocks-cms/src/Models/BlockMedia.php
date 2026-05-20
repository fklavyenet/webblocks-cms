<?php

namespace WebBlocks\Cms\Models;

use App\Models\Block;
use App\Models\BlockGalleryItemTranslation;
use App\Models\Media;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BlockMedia extends Model
{
    use HasFactory;

    protected $table = 'block_media';

    protected $fillable = [
        'block_id',
        'media_id',
        'asset_id',
        'role',
        'position',
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
