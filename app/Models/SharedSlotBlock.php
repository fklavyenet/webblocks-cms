<?php

namespace App\Models;

use App\Support\Search\ReindexesPublicSearch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SharedSlotBlock extends Model
{
    use HasFactory;
    use ReindexesPublicSearch;

    protected static function booted(): void
    {
        static::saved(function (self $assignment): void {
            static::refreshSearchForSharedSlot($assignment->shared_slot_id);
        });

        static::deleted(function (self $assignment): void {
            static::refreshSearchForSharedSlot($assignment->shared_slot_id);
        });
    }

    protected $fillable = [
        'shared_slot_id',
        'block_id',
        'parent_id',
        'sort_order',
    ];

    public function sharedSlot(): BelongsTo
    {
        return $this->belongsTo(SharedSlot::class);
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }
}
