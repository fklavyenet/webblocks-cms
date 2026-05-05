<?php

namespace App\Models;

use App\Support\SharedSlots\SharedSlotSchema;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SharedSlotRevision extends Model
{
    use HasFactory;

    protected $fillable = [
        'shared_slot_id',
        'site_id',
        'user_id',
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
        return $this->belongsTo(User::class, 'user_id');
    }

    public function restoredFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'restored_from_shared_slot_revision_id');
    }

    public function labelText(): string
    {
        return $this->label ?: str($this->source_event)->replace('_', ' ')->headline()->toString();
    }

    public function eventText(): string
    {
        return str($this->source_event)->replace('_', ' ')->headline()->toString();
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        if (! app(SharedSlotSchema::class)->revisionsTableExists()) {
            return null;
        }

        return parent::resolveRouteBinding($value, $field);
    }
}
