<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SharedSlot extends Model
{
    use HasFactory;

    public const COMMON_SLOT_NAMES = ['header', 'sidebar', 'main', 'footer'];

    protected static function booted(): void
    {
        static::saving(function (self $sharedSlot): void {
            $sharedSlot->handle = str((string) $sharedSlot->handle)->slug()->toString();
            $sharedSlot->slot_name = str((string) $sharedSlot->slot_name)->slug()->toString();
        });
    }

    protected $fillable = [
        'site_id',
        'name',
        'handle',
        'slot_name',
        'public_shell',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function slotBlocks(): HasMany
    {
        return $this->hasMany(SharedSlotBlock::class)->orderBy('sort_order')->orderBy('id');
    }

    public function blocks(): BelongsToMany
    {
        return $this->belongsToMany(Block::class, 'shared_slot_blocks')
            ->withPivot(['id', 'parent_id', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderBy('blocks.id');
    }

    public function pageSlots(): HasMany
    {
        return $this->hasMany(PageSlot::class);
    }

    public function statusLabel(): string
    {
        return $this->is_active ? 'Active' : 'Inactive';
    }

    public function statusBadgeClass(): string
    {
        return $this->is_active ? 'wb-status-active' : 'wb-status-pending';
    }

    public function publicShellLabel(): string
    {
        return $this->public_shell ?: 'Any';
    }

    public function slotLabel(): string
    {
        return $this->slot_name ?: 'Any';
    }
}
