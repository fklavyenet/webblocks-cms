<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PageSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_id',
        'slot_type_id',
        'sort_order',
    ];

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function slotType(): BelongsTo
    {
        return $this->belongsTo(SlotType::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class, 'slot_type_id', 'slot_type_id')
            ->where('page_id', $this->page_id)
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
