<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteExport extends Model
{
    use HasFactory;

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'site_id',
        'user_id',
        'status',
        'includes_media',
        'archive_disk',
        'archive_path',
        'archive_name',
        'archive_size_bytes',
        'summary_json',
        'manifest_json',
        'output_log',
        'failure_message',
    ];

    protected function casts(): array
    {
        return [
            'includes_media' => 'boolean',
            'summary_json' => 'array',
            'manifest_json' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function statusLabel(): string
    {
        return str($this->status)->replace('_', ' ')->title()->toString();
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED => 'wb-status-active',
            self::STATUS_RUNNING => 'wb-status-pending',
            default => 'wb-status-danger',
        };
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
}
