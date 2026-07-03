<?php

namespace WebBlocks\Cms\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class IconCatalogItem extends CmsModel
{
  use HasFactory;

  protected $fillable = [
    'source',
    'slug',
    'label',
    'css_class',
    'categories',
    'contexts',
    'keywords',
    'is_active',
    'sort_order',
    'synced_at',
  ];

  protected function casts(): array
  {
    return [
      'categories' => 'array',
      'contexts' => 'array',
      'keywords' => 'array',
      'is_active' => 'boolean',
      'synced_at' => 'datetime',
    ];
  }

  protected static function booted(): void
  {
    static::saving(function (self $icon): void {
      $icon->source = trim(Str::lower((string) $icon->source)) ?: 'webblocks-ui';
      $icon->slug = self::normalizeSlug((string) $icon->slug) ?? '';
      $icon->label = trim((string) $icon->label) ?: Str::of((string) $icon->slug)->replace('-', ' ')->title()->toString();
      $icon->css_class = self::normalizeCssClass($icon->css_class, $icon->slug);
      $icon->categories = self::normalizeTags($icon->categories);
      $icon->contexts = self::normalizeTags($icon->contexts);
      $icon->keywords = self::normalizeKeywords($icon->keywords);
      $icon->sort_order = (int) ($icon->sort_order ?? 0);
    });
  }

  public function scopeActive(Builder $query): Builder
  {
    return $query->where('is_active', true);
  }

  public function scopeSearch(Builder $query, string $term): Builder
  {
    $term = trim($term);

    if ($term === '') {
      return $query;
    }

    return $query->where(function (Builder $builder) use ($term): void {
      $builder
        ->where('label', 'like', '%'.$term.'%')
        ->orWhere('slug', 'like', '%'.$term.'%')
        ->orWhere('css_class', 'like', '%'.$term.'%')
        ->orWhere('keywords', 'like', '%'.$term.'%');
    });
  }

  public function scopeForSource(Builder $query, string $source): Builder
  {
    $source = trim(Str::lower($source));

    if ($source === '') {
      return $query;
    }

    return $query->where('source', $source);
  }

  public function scopeTagged(Builder $query, string $tag): Builder
  {
    $tag = self::normalizeTag($tag);

    if ($tag === null) {
      return $query;
    }

    return $query->where(function (Builder $builder) use ($tag): void {
      $builder
        ->where('categories', 'like', '%"'.$tag.'"%')
        ->orWhere('contexts', 'like', '%"'.$tag.'"%');
    });
  }

  public function isTagged(string $tag): bool
  {
    $tag = self::normalizeTag($tag);

    if ($tag === null) {
      return false;
    }

    return in_array($tag, $this->categories ?? [], true)
      || in_array($tag, $this->contexts ?? [], true);
  }

  public static function normalizeSlug(?string $slug): ?string
  {
    $slug = trim(Str::lower((string) $slug));

    if ($slug === '') {
      return null;
    }

    if (str_starts_with($slug, 'wb-icon-')) {
      $slug = substr($slug, strlen('wb-icon-'));
    }

    $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug) ?? '';
    $slug = trim($slug, '-');

    return $slug !== '' ? $slug : null;
  }

  public static function normalizeCssClass(?string $cssClass, string $slug): string
  {
    $cssClass = trim((string) $cssClass);
    $slug = self::normalizeSlug($slug) ?? $slug;

    if ($cssClass === '') {
      return 'wb-icon-'.$slug;
    }

    if (! str_starts_with($cssClass, 'wb-icon-')) {
      return 'wb-icon-'.ltrim($cssClass, '-');
    }

    return $cssClass;
  }

  public static function normalizeTags(array|string|null $values): ?array
  {
    $values = is_array($values) ? $values : explode(',', (string) ($values ?? ''));

    $tags = collect($values)
      ->map(fn ($value) => self::normalizeTag($value))
      ->filter()
      ->unique()
      ->values()
      ->all();

    return $tags === [] ? null : $tags;
  }

  public static function normalizeKeywords(array|string|null $values): ?array
  {
    $values = is_array($values) ? $values : explode(',', (string) ($values ?? ''));

    $keywords = collect($values)
      ->map(function ($value): ?string {
        $keyword = trim(Str::lower((string) $value));

        return $keyword !== '' ? $keyword : null;
      })
      ->filter()
      ->unique()
      ->values()
      ->all();

    return $keywords === [] ? null : $keywords;
  }

  public static function normalizeTag(mixed $value): ?string
  {
    $tag = trim(Str::lower((string) $value));

    if ($tag === '') {
      return null;
    }

    $tag = preg_replace('/[^a-z0-9-]+/', '-', $tag) ?? '';
    $tag = trim($tag, '-');

    return $tag !== '' ? $tag : null;
  }
}
