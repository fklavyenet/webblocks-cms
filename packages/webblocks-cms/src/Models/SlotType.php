<?php

namespace WebBlocks\Cms\Models;

use App\Models\Block;
use App\Models\PageLayoutSlot;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SlotType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'axis',
        'is_system',
        'sort_order',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class);
    }

    public function pageLayoutSlots(): HasMany
    {
        return $this->hasMany(PageLayoutSlot::class);
    }
}
