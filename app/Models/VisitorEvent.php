<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitorEvent extends Model
{
    use HasFactory;

    public const TRACKING_MODE_BASIC = 'basic';

    public const TRACKING_MODE_FULL = 'full';

    protected $fillable = [
        'site_id',
        'page_id',
        'locale_id',
        'path',
        'tracking_mode',
        'referrer',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'device_type',
        'browser_family',
        'os_family',
        'session_key',
        'ip_hash',
        'visited_at',
    ];

    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
        ];
    }

    public function isFullTracking(): bool
    {
        return $this->tracking_mode === self::TRACKING_MODE_FULL;
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function locale(): BelongsTo
    {
        return $this->belongsTo(Locale::class);
    }
}
