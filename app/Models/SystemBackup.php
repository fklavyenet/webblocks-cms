<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemBackup extends Model
{
    use HasFactory;

    public const TYPE_MANUAL = 'manual';

    public const TYPE_UPLOADED = 'uploaded';

    public const TYPE_RESTORE_SAFETY = 'restore_safety';

    public const TYPE_PRE_UPDATE = 'pre_update';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'type',
        'status',
        'label',
        'includes_database',
        'includes_uploads',
        'archive_disk',
        'archive_path',
        'archive_filename',
        'archive_size_bytes',
        'started_at',
        'finished_at',
        'duration_ms',
        'summary',
        'output',
        'triggered_by_user_id',
        'error_message',
    ];

    protected $casts = [
        'includes_database' => 'bool',
        'includes_uploads' => 'bool',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_user_id');
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isDeletable(): bool
    {
        return $this->isFailed() || $this->isRunning();
    }

    public function archiveRelativePath(): ?string
    {
        return $this->archive_path;
    }

    public function isRecentSuccessful(int $hours = 24): bool
    {
        return $this->isSuccessful()
            && $this->finished_at !== null
            && $this->finished_at->gte(now()->subHours($hours));
    }

    public function statusLabel(): string
    {
        return str_replace('_', ' ', $this->status);
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED => 'wb-status-active',
            self::STATUS_RUNNING => 'wb-status-pending',
            default => 'wb-status-danger',
        };
    }

    public function contentsLabel(): string
    {
        return collect([
            $this->includes_database ? 'DB' : null,
            $this->includes_uploads ? 'Uploads' : null,
        ])->filter()->implode(' + ');
    }

    public function humanArchiveSize(): string
    {
        $bytes = $this->archive_size_bytes;

        if ($bytes === null) {
            return '-';
        }

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 1).' KB';
        }

        if ($bytes < 1073741824) {
            return number_format($bytes / 1048576, 1).' MB';
        }

        return number_format($bytes / 1073741824, 1).' GB';
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
