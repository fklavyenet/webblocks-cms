<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;
use InvalidArgumentException;

class PageSlot extends Model
{
    use HasFactory;

    public const SOURCE_TYPE_PAGE = 'page';

    public const SOURCE_TYPE_SHARED_SLOT = 'shared_slot';

    public const SOURCE_TYPE_DISABLED = 'disabled';

    private const OBSOLETE_SETTINGS_KEYS = [
        'wrapper_element',
        'wrapper_preset',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $slot): void {
            $slot->settings = self::sanitizeSettings($slot->settings);
            $slot->source_type = self::normalizeSourceType($slot->source_type);

            if ($slot->source_type !== self::SOURCE_TYPE_SHARED_SLOT) {
                $slot->shared_slot_id = null;
            }

            if ($slot->source_type === self::SOURCE_TYPE_SHARED_SLOT && ! $slot->shared_slot_id) {
                throw new InvalidArgumentException('Shared slot page slots must reference a shared slot.');
            }
        });
    }

    protected $fillable = [
        'page_id',
        'slot_type_id',
        'source_type',
        'shared_slot_id',
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

    public static function sourceTypes(): array
    {
        return [
            self::SOURCE_TYPE_PAGE,
            self::SOURCE_TYPE_SHARED_SLOT,
            self::SOURCE_TYPE_DISABLED,
        ];
    }

    public static function normalizeSourceType(mixed $sourceType): string
    {
        $normalized = strtolower(trim((string) ($sourceType ?: self::SOURCE_TYPE_PAGE)));

        if (! in_array($normalized, self::sourceTypes(), true)) {
            throw new InvalidArgumentException('Unsupported page slot source type ['.$normalized.'].');
        }

        return $normalized;
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

    public function sharedSlot(): BelongsTo
    {
        return $this->belongsTo(SharedSlot::class);
    }

    public function usesPageOwnedBlocks(): bool
    {
        return self::normalizeRuntimeSourceType($this->source_type) === self::SOURCE_TYPE_PAGE;
    }

    public static function normalizeRuntimeSourceType(mixed $sourceType): string
    {
        $normalized = trim((string) $sourceType);

        if ($normalized === '') {
            return self::SOURCE_TYPE_PAGE;
        }

        return self::normalizeSourceType($normalized);
    }

    public function blocks(): HasMany
    {
        $query = $this->hasMany(Block::class, 'slot_type_id', 'slot_type_id')
            ->where('page_id', $this->page_id)
            ->orderBy('sort_order')
            ->orderBy('id');

        if (! $this->usesPageOwnedBlocks()) {
            $query->whereRaw('1 = 0');
        }

        return $query;
    }
}
