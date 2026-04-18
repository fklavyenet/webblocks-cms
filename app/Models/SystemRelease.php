<?php

namespace App\Models;

use Database\Factories\SystemReleaseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SystemRelease extends Model
{
    /** @use HasFactory<SystemReleaseFactory> */
    use HasFactory;

    protected $fillable = [
        'product',
        'channel',
        'version',
        'version_normalized',
        'release_name',
        'description',
        'changelog',
        'download_url',
        'checksum_sha256',
        'is_critical',
        'is_security',
        'published_at',
        'supported_from_version',
        'supported_until_version',
        'min_php_version',
        'min_laravel_version',
        'metadata',
    ];

    protected $casts = [
        'is_critical' => 'boolean',
        'is_security' => 'boolean',
        'published_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function newFactory(): SystemReleaseFactory
    {
        return SystemReleaseFactory::new();
    }

    public function scopeForProduct(Builder $query, string $product): Builder
    {
        return $query->where('product', $product);
    }

    public function scopeForChannel(Builder $query, string $channel): Builder
    {
        return $query->where('channel', $channel);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}
