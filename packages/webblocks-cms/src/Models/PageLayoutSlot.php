<?php

namespace WebBlocks\Cms\Models;

use App\Models\PageLayout;
use App\Models\SlotType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageLayoutSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_layout_id',
        'slot_type_id',
        'slot_name',
        'label',
        'description',
        'html_element',
        'html_id',
        'html_classes',
        'before_html',
        'start_html',
        'end_html',
        'after_html',
        'is_required',
        'is_active',
        'is_system',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    public function pageLayout(): BelongsTo
    {
        return $this->belongsTo(PageLayout::class);
    }

    public function slotType(): BelongsTo
    {
        return $this->belongsTo(SlotType::class);
    }

    public function statusLabel(): string
    {
        return $this->is_active ? 'Active' : 'Inactive';
    }

    public function statusBadgeClass(): string
    {
        return $this->is_active ? 'wb-status-active' : 'wb-status-pending';
    }
}
