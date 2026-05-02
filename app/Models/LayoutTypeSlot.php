<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LayoutTypeSlot extends Model
{
    use HasFactory;

    public const OWNERSHIP_LAYOUT = 'layout';

    public const OWNERSHIP_PAGE = 'page';

    protected $fillable = [
        'layout_type_id',
        'slot_type_id',
        'sort_order',
        'ownership',
        'wrapper_element',
        'wrapper_preset',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function layoutType(): BelongsTo
    {
        return $this->belongsTo(LayoutType::class);
    }

    public function slotType(): BelongsTo
    {
        return $this->belongsTo(SlotType::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class)->orderBy('sort_order')->orderBy('id');
    }

    public static function ownershipOptions(): array
    {
        return [self::OWNERSHIP_LAYOUT, self::OWNERSHIP_PAGE];
    }

    public function ownership(): string
    {
        return in_array($this->attributes['ownership'] ?? null, self::ownershipOptions(), true)
            ? $this->attributes['ownership']
            : self::OWNERSHIP_PAGE;
    }

    public function isLayoutOwned(): bool
    {
        return $this->ownership() === self::OWNERSHIP_LAYOUT;
    }

    public function isPageOwned(): bool
    {
        return $this->ownership() === self::OWNERSHIP_PAGE;
    }

    public function wrapperElement(): string
    {
        $element = strtolower(trim((string) $this->wrapper_element));

        if ($element === '') {
            return PageSlot::defaultWrapperElementForSlug($this->slotType?->slug);
        }

        return in_array($element, PageSlot::allowedWrapperElements(), true)
            ? $element
            : PageSlot::defaultWrapperElementForSlug($this->slotType?->slug);
    }

    public function wrapperPreset(): string
    {
        return PageSlot::normalizeWrapperPreset($this->wrapper_preset ?: 'default');
    }
}
