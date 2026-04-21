<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ContactMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'block_id',
        'page_id',
        'name',
        'email',
        'subject',
        'message',
        'status',
        'source_url',
        'ip_address',
        'user_agent',
        'referer',
        'notification_enabled',
        'notification_recipient',
        'notification_sent_at',
        'notification_error',
    ];

    protected function casts(): array
    {
        return [
            'notification_enabled' => 'boolean',
            'notification_sent_at' => 'datetime',
        ];
    }

    public static function statuses(): array
    {
        return ['new', 'read', 'replied', 'archived', 'spam'];
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class)->with('translations');
    }

    public function statusClass(): string
    {
        return match ($this->status) {
            'new' => 'wb-status-info',
            'read' => 'wb-status-pending',
            'replied' => 'wb-status-active',
            'archived' => 'wb-status-pending',
            'spam' => 'wb-status-danger',
            default => 'wb-status-info',
        };
    }

    public function notificationLabel(): string
    {
        if (! $this->notification_enabled) {
            return 'Skipped';
        }

        if ($this->notification_sent_at) {
            return 'Sent';
        }

        if (filled($this->notification_error)) {
            return 'Failed';
        }

        return 'Pending';
    }

    public function notificationClass(): string
    {
        if (! $this->notification_enabled) {
            return 'wb-status-pending';
        }

        if ($this->notification_sent_at) {
            return 'wb-status-active';
        }

        if (filled($this->notification_error)) {
            return 'wb-status-danger';
        }

        return 'wb-status-info';
    }

    public function sourceLabel(): string
    {
        if ($this->page?->title) {
            return $this->page->title;
        }

        return $this->source_url ?: '-';
    }

    public function sourcePath(): string
    {
        if (! $this->source_url) {
            return '-';
        }

        $path = parse_url($this->source_url, PHP_URL_PATH);

        if (! is_string($path) || trim($path) === '') {
            return Str::limit($this->source_url, 48);
        }

        return $path;
    }
}
