<?php

namespace WebBlocks\Cms\Models;

use App\Models\Page;
use App\Models\PageRevision as RootPageRevision;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageRevision extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_id',
        'site_id',
        'created_by',
        'created_by_user_id',
        'source',
        'event',
        'label',
        'reason',
        'snapshot',
        'restored_from_page_revision_id',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
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
        return $this->belongsTo(RootPageRevision::class, 'restored_from_page_revision_id');
    }

    public function labelText(): string
    {
        return $this->label ?: 'Page revision';
    }

    public function eventText(): string
    {
        return $this->event
            ? str($this->event)->replace('_', ' ')->headline()->toString()
            : 'Not recorded';
    }

    public function sourceText(): string
    {
        return $this->source
            ? str($this->source)->replace('_', ' ')->headline()->toString()
            : 'Not recorded';
    }
}
