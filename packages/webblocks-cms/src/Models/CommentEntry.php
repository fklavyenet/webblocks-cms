<?php

namespace WebBlocks\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CommentEntry extends Model
{
  use HasFactory;

  protected $fillable = [
    'site_id',
    'page_id',
    'block_id',
    'author_name',
    'body',
    'status',
    'source_url',
    'visitor_hash',
    'ip_hash',
    'user_agent',
    'spam_score',
    'spam_reasons',
    'approved_at',
    'approved_by_user_id',
  ];

  protected function casts(): array
  {
    return [
      'spam_score' => 'integer',
      'spam_reasons' => 'array',
      'approved_at' => 'datetime',
    ];
  }

  public static function statuses(): array
  {
    return ['pending', 'approved', 'rejected', 'spam', 'hidden'];
  }

  public function site(): BelongsTo
  {
    return $this->belongsTo(Site::class);
  }

  public function page(): BelongsTo
  {
    return $this->belongsTo(Page::class)->with('translations');
  }

  public function block(): BelongsTo
  {
    return $this->belongsTo(Block::class);
  }

  public function approvedByUser(): BelongsTo
  {
    return $this->belongsTo(config('auth.providers.users.model', \App\Models\User::class), 'approved_by_user_id');
  }

  public function statusClass(): string
  {
    return match ($this->status) {
      'approved' => 'wb-status-active',
      'rejected', 'hidden' => 'wb-status-pending',
      'spam' => 'wb-status-danger',
      default => 'wb-status-info',
    };
  }

  public function sourceLabel(): string
  {
    return $this->page?->title ?: ($this->sourcePath() ?: '-');
  }

  public function sourcePath(): string
  {
    if (! $this->source_url) {
      return '-';
    }

    $path = parse_url($this->source_url, PHP_URL_PATH);

    return is_string($path) && trim($path) !== '' ? $path : Str::limit($this->source_url, 48);
  }

  public function spamReasonLabels(): array
  {
    return is_array($this->spam_reasons)
      ? array_values(array_filter($this->spam_reasons, 'is_string'))
      : [];
  }
}
