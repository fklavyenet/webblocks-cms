<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemUpdateRun extends Model
{
    use HasFactory;

    public const STATUS_SUCCESS = 'success';

    public const STATUS_SUCCESS_WITH_WARNINGS = 'success_with_warnings';

    public const STATUS_FAILED = 'failed';

    public const STATUS_PENDING = 'pending';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'from_version',
        'to_version',
        'status',
        'summary',
        'output',
        'warning_count',
        'started_at',
        'finished_at',
        'duration_ms',
        'triggered_by_user_id',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function statusLabel(): string
    {
        return str_replace('_', ' ', $this->status);
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_SUCCESS => 'wb-status-active',
            self::STATUS_SUCCESS_WITH_WARNINGS => 'wb-status-pending',
            self::STATUS_PENDING => 'wb-status-pending',
            default => 'wb-status-danger',
        };
    }

    public function durationLabel(): string
    {
        if ($this->duration_ms === null) {
            return '-';
        }

        if ($this->duration_ms < 1000) {
            return number_format($this->duration_ms).' ms';
        }

        return number_format($this->duration_ms / 1000, 1).' s';
    }
}
