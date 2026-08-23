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
      'applied_at' => 'datetime',
      'discarded_at' => 'datetime',
    ];
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
