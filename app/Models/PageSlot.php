<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;

class PageSlot extends Model
{
    use HasFactory;

    private const OBSOLETE_SETTINGS_KEYS = [
        'wrapper_element',
        'wrapper_preset',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $slot): void {
            $slot->settings = self::sanitizeSettings($slot->settings);
        });
    }

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

    public static function sanitizeSettings(mixed $settings): ?array
    {
        if (! is_array($settings)) {
            return null;
        }

        $sanitized = Arr::except($settings, self::OBSOLETE_SETTINGS_KEYS);

        return $sanitized === [] ? null : $sanitized;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get(is_array($this->settings) ? $this->settings : [], $key, $default);
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
