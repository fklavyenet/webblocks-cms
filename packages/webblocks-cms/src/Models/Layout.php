<?php

namespace WebBlocks\Cms\Models;

use WebBlocks\Cms\Models\LayoutType;
use WebBlocks\Cms\Models\Page;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Layout extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'layout_type_id',
    ];

    public function layoutType(): BelongsTo
    {
        return $this->belongsTo(LayoutType::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public static function defaultLayout(): self
    {
        return static::query()->firstOrCreate(
            ['slug' => 'default-layout'],
            ['name' => 'Default Layout']
        );
    }
}
