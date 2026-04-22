<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteImport extends Model
{
    use HasFactory;

    public const STATUS_RUNNING = 'running';

    public const STATUS_VALIDATED = 'validated';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'status',
        'source_archive_name',
        'archive_disk',
        'archive_path',
        'target_site_id',
        'imported_site_handle',
        'imported_site_domain',
        'summary_json',
        'manifest_json',
        'output_log',
        'failure_message',
    ];

    protected function casts(): array
    {
        return [
            'summary_json' => 'array',
            'manifest_json' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function targetSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'target_site_id');
    }

    public function isValidated(): bool
    {
        return $this->status === self::STATUS_VALIDATED;
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
            self::STATUS_VALIDATED, self::STATUS_RUNNING => 'wb-status-pending',
            default => 'wb-status-danger',
        };
    }
}
