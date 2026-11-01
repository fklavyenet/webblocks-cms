<?php

namespace WebBlocks\Cms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageRevisionCandidate extends CmsModel
{
  public const STATUS_READY = 'ready';

  public const STATUS_APPLIED = 'applied';

  public const STATUS_DISCARDED = 'discarded';

  protected $fillable = [
    'page_id',
    'restore_scope',
    'restore_locale_id',
    'source_fingerprint',
    'revision_fingerprint',
    'preview_fingerprint',
    'page_revision_id',
    'candidate_page_id',
    'created_by_user_id',
    'status',
    'source_updated_at',
    'applied_at',
    'discarded_at',
  ];

  protected function casts(): array
  {
    return [
      'source_updated_at' => 'datetime',
      'restore_locale_id' => 'integer',
      'applied_at' => 'datetime',
      'discarded_at' => 'datetime',
    ];
  }

  public function previewUrl(bool $absolute = true): ?string
  {
    if (! $this->candidatePage) {
      return null;
    }
    $parameters = ['page' => $this->candidatePage];
    if (($this->restore_scope ?? 'full_page') !== 'full_page') {
      $code = Locale::find($this->restore_locale_id)?->code;
      if ($code === null) {
        return null;
      }
      $parameters['locale'] = $code;
    }

    return route('admin.pages.preview', $parameters, $absolute);
  }

  public function page(): BelongsTo
  {
    return $this->belongsTo(Page::class);
  }

  public function revision(): BelongsTo
  {
    return $this->belongsTo(PageRevision::class, 'page_revision_id');
  }

  public function candidatePage(): BelongsTo
  {
    return $this->belongsTo(Page::class, 'candidate_page_id');
  }

  public function createdByUser(): BelongsTo
  {
    return $this->belongsTo(User::class, 'created_by_user_id');
  }
}
