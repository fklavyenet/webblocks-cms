<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LayoutType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_system',
        'sort_order',
        'status',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'settings' => 'array',
        ];
    }

    public function layouts(): HasMany
    {
        return $this->hasMany(Layout::class);
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(LayoutTypeSlot::class)->orderBy('sort_order')->orderBy('id');
    }

    public function publicShellPreset(): string
    {
        return Page::normalizePublicShellPreset($this->settings['public_shell'] ?? 'default');
    }
}
