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

    public static function acceptedWrapperPresets(): array
    {
        return ['default', 'plain', 'docs-navbar', 'docs-main', 'docs-sidebar'];
    }

    public static function normalizeWrapperPreset(?string $preset): string
    {
        $normalized = trim((string) $preset);

        return in_array($normalized, self::acceptedWrapperPresets(), true) ? $normalized : 'default';
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get(is_array($this->settings) ? $this->settings : [], $key, $default);
    }

    public function wrapperPreset(): string
    {
        return self::normalizeWrapperPreset((string) $this->setting('wrapper_preset', 'default'));
    }

    public function wrapperElement(): ?string
    {
        $element = trim((string) $this->setting('wrapper_element', ''));

        return in_array($element, ['header', 'main', 'aside', 'footer', 'div'], true) ? $element : null;
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
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
