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
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

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
            ->whereNull('layout_type_slot_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public static function allowedWrapperElements(): array
    {
        return ['div', 'header', 'main', 'aside', 'footer'];
    }

    public static function allowedWrapperPresets(): array
    {
        return ['default', 'docs-navbar', 'docs-sidebar', 'docs-main', 'plain'];
    }

    public static function acceptedWrapperPresets(): array
    {
        return array_merge(self::allowedWrapperPresets(), ['dashboard-navbar', 'dashboard-sidebar', 'dashboard-main']);
    }

    public static function defaultWrapperElementForSlug(?string $slug): string
    {
        return match ($slug) {
            'header' => 'header',
            'sidebar' => 'aside',
            'main' => 'main',
            'footer' => 'footer',
            default => 'div',
        };
    }

    public function wrapperElement(): string
    {
        $element = strtolower((string) ($this->settings['wrapper_element'] ?? 'div'));

        return in_array($element, self::allowedWrapperElements(), true) ? $element : 'div';
    }

    public function wrapperPreset(): string
    {
        return self::normalizeWrapperPreset($this->settings['wrapper_preset'] ?? 'default');
    }

    public static function normalizeWrapperPreset(mixed $preset): string
    {
        return match (strtolower(trim((string) $preset))) {
            'docs-navbar', 'dashboard-navbar' => 'docs-navbar',
            'docs-sidebar', 'dashboard-sidebar' => 'docs-sidebar',
            'docs-main', 'dashboard-main' => 'docs-main',
            'plain' => 'plain',
            default => 'default',
        };
    }
}
