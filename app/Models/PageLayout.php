<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PageLayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'handle',
        'name',
        'description',
        'is_system',
        'is_active',
        'sort_order',
        'body_class',
        'shell_type',
        'slot_schema',
        'wrapper_schema',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'slot_schema' => 'array',
            'wrapper_schema' => 'array',
        ];
    }

    public function statusLabel(): string
    {
        return $this->is_active ? 'Active' : 'Inactive';
    }

    public function statusBadgeClass(): string
    {
        return $this->is_active ? 'wb-status-active' : 'wb-status-pending';
    }

    public function ownershipLabel(): string
    {
        return $this->is_system ? 'System' : 'Custom';
    }

    public function layoutSlots(): HasMany
    {
        return $this->hasMany(PageLayoutSlot::class)
            ->orderBy('sort_order')
            ->orderBy('slot_name');
    }
}
