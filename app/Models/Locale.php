<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Locale extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'is_default',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_enabled' => 'boolean',
        ];
    }

    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'site_locales')
            ->withPivot('is_enabled')
            ->withTimestamps();
    }

    public function siteLocales(): HasMany
    {
        return $this->hasMany(SiteLocale::class);
    }

    public function pageTranslations(): HasMany
    {
        return $this->hasMany(PageTranslation::class);
    }
}
