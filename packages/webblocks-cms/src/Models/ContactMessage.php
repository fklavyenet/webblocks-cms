<?php

namespace WebBlocks\Cms\Models;

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
    'spam_score',
    'spam_reasons',
    'notification_enabled',
    'notification_recipient',
    'notification_recipient_source',
    'notification_status',
    'notification_sent_at',
    'notification_error',
    'notification_reason',
  ];

  protected function casts(): array
  {
    return [
      'spam_reasons' => 'array',
      'spam_score' => 'integer',
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
    return match ($this->resolvedNotificationStatus()) {
      'sent' => 'Sent',
      'failed' => 'Failed',
      'skipped' => 'Skipped',
      'not_configured' => 'Not configured',
      default => 'Pending',
    };
  }

  public function notificationClass(): string
  {
    return match ($this->resolvedNotificationStatus()) {
      'sent' => 'wb-status-active',
      'failed' => 'wb-status-danger',
      'skipped', 'not_configured' => 'wb-status-pending',
      default => 'wb-status-info',
    };
  }

  public function resolvedNotificationStatus(): string
  {
    if (in_array($this->notification_status, ['sent', 'failed', 'skipped', 'not_configured', 'pending'], true)) {
      return $this->notification_status;
    }

    if (! $this->notification_enabled) {
      return 'skipped';
    }

    if ($this->notification_sent_at) {
      return 'sent';
    }

    if (filled($this->notification_error)) {
      return 'failed';
    }

    return 'pending';
  }

  public function notificationDetail(): ?string
  {
    return $this->notification_error ?: $this->notification_reason;
  }

  public function notificationSourceLabel(): string
  {
    return match ($this->notification_recipient_source) {
      'block' => 'Block recipient',
      'site' => 'Site contact recipient',
      'CONTACT_RECIPIENT_EMAIL' => 'CONTACT_RECIPIENT_EMAIL',
      'MAIL_FROM_ADDRESS' => 'MAIL_FROM_ADDRESS fallback',
      'contact_form' => 'Contact Form recipient',
      default => '-',
    };
  }

  public function hasLegacyNotificationState(): bool
  {
    return ! in_array($this->notification_status, ['sent', 'failed', 'skipped', 'not_configured', 'pending'], true);
  }

  public function spamReasonLabels(): array
  {
    $reasons = $this->spam_reasons;

    return is_array($reasons) ? array_values(array_filter($reasons, 'is_string')) : [];
  }

  public function detailTitleName(): string
  {
    foreach ([$this->name, $this->email, $this->subject] as $candidate) {
      $value = trim((string) $candidate);

      if ($value !== '') {
        return $value;
      }
    }

    return '#'.$this->id;
  }

  public function detailPageTitle(): string
  {
    return 'Contact Message: '.$this->detailTitleName();
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
