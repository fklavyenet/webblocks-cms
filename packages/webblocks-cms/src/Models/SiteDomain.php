<?php

namespace WebBlocks\Cms\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use WebBlocks\Cms\Support\Sites\SiteDomainNormalizer;

class SiteDomain extends Model
{
  use HasFactory;

  public const STATUS_ACTIVE = 'active';

  public const STATUS_INACTIVE = 'inactive';

  protected $fillable = [
    'site_id',
    'domain',
    'is_primary',
    'redirect_to_primary',
    'status',
  ];

  protected function casts(): array
  {
    return [
      'is_primary' => 'boolean',
      'redirect_to_primary' => 'boolean',
    ];
  }

  protected static function booted(): void
  {
    static::saving(function (self $siteDomain): void {
      $siteDomain->domain = app(SiteDomainNormalizer::class)->normalize($siteDomain->domain);
      $siteDomain->status = $siteDomain->normalizeStatus($siteDomain->status);
    });
  }

  public function site(): BelongsTo
  {
    return $this->belongsTo(Site::class);
  }

  public function scopeActive(Builder $query): Builder
  {
    return $query->where('status', self::STATUS_ACTIVE);
  }

  public function isActive(): bool
  {
    return $this->status === self::STATUS_ACTIVE;
  }

  public function normalizeStatus(?string $status): string
  {
    return strtolower(trim((string) $status)) === self::STATUS_INACTIVE
          ? self::STATUS_INACTIVE
          : self::STATUS_ACTIVE;
  }
}
