<?php

namespace WebBlocks\Cms\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSchema;

class SharedSlotRevision extends CmsModel
{
  use HasFactory;

  protected $fillable = [
    'shared_slot_id',
    'site_id',
    'user_id',
    'created_by_user_id',
    'source',
    'event',
    'source_event',
    'label',
    'summary',
    'snapshot',
    'restored_from_shared_slot_revision_id',
  ];

  protected function casts(): array
  {
    return [
      'snapshot' => 'array',
    ];
  }

  public function sharedSlot(): BelongsTo
  {
    return $this->belongsTo(SharedSlot::class);
  }

  public function site(): BelongsTo
  {
    return $this->belongsTo(Site::class);
  }

  public function actor(): BelongsTo
  {
    return $this->belongsTo(User::class, 'created_by_user_id');
  }

  public function createdByUser(): BelongsTo
  {
    return $this->belongsTo(User::class, 'created_by_user_id');
  }

  public function restoredFrom(): BelongsTo
  {
    return $this->belongsTo(self::class, 'restored_from_shared_slot_revision_id');
  }

  public function labelText(): string
  {
    return $this->label ?: $this->eventText();
  }

  public function eventText(): string
  {
    $value = $this->event ?: $this->source_event;

    return $value
      ? str($value)->replace('_', ' ')->headline()->toString()
      : 'Not recorded';
  }

  public function sourceText(): string
  {
    return $this->source
      ? str($this->source)->replace('_', ' ')->headline()->toString()
      : 'Not recorded';
  }

  public function resolveRouteBinding($value, $field = null): ?Model
  {
    if (! app(SharedSlotSchema::class)->revisionsTableExists()) {
      return null;
    }

    return parent::resolveRouteBinding($value, $field);
  }
}
