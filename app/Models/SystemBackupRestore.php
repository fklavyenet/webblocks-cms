<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemBackupRestore extends Model
{
    use HasFactory;

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'source_backup_id',
        'source_archive_disk',
        'source_archive_path',
        'source_archive_filename',
        'safety_backup_id',
        'status',
        'restored_parts',
        'manifest',
        'started_at',
        'finished_at',
        'duration_ms',
        'summary',
        'output',
        'triggered_by_user_id',
        'error_message',
    ];

    protected $casts = [
        'restored_parts' => 'array',
        'manifest' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function sourceBackup(): BelongsTo
    {
        return $this->belongsTo(SystemBackup::class, 'source_backup_id');
    }

    public function safetyBackup(): BelongsTo
    {
        return $this->belongsTo(SystemBackup::class, 'safety_backup_id');
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function statusBadgeClass(): string
    {
        return $this->status === self::STATUS_COMPLETED
            ? 'wb-status-active'
            : 'wb-status-danger';
    }

    public function statusLabel(): string
    {
        return str_replace('_', ' ', $this->status);
    }

    public function restoredPartsLabel(): string
    {
        return collect($this->restored_parts ?? [])->map(function (string $part): string {
            return match ($part) {
                'database' => 'DB',
                'uploads' => 'Uploads',
                default => $part,
            };
        })->implode(' + ');
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
