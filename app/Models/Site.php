<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'handle',
        'domain',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function locales(): BelongsToMany
    {
        return $this->belongsToMany(Locale::class, 'site_locales')
            ->withPivot('is_enabled')
            ->withTimestamps();
    }

    public function siteLocales(): HasMany
    {
        return $this->hasMany(SiteLocale::class);
    }
}
