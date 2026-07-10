<?php

namespace WebBlocks\Cms\Plugins\WebBlocksCommerce\Support\I18n;

use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Plugins\WebBlocksCommerce\Models\CommerceProduct;

/**
 * Resolves storefront-facing product content for a locale, sharing the CMS
 * Site+Locale system. The base product row is the default/fallback; a
 * per-locale translation row overrides title/description when present.
 */
class ProductLocalizer
{
  /** @var array<string, int|null> */
  private array $localeIdCache = [];

  public function localeId(?string $code): ?int
  {
    $code = Locale::normalizeCode($code);

    if ($code === null) {
      return null;
    }

    if (! array_key_exists($code, $this->localeIdCache)) {
      $this->localeIdCache[$code] = Locale::query()->where('code', $code)->value('id');
    }

    return $this->localeIdCache[$code];
  }

  /**
   * @return array{title: string, description: ?string}
   */
  public function localize(CommerceProduct $product, ?string $localeCode): array
  {
    $base = [
      'title' => (string) $product->title,
      'description' => $product->description,
    ];

    $localeId = $this->localeId($localeCode);

    if ($localeId === null) {
      return $base;
    }

    $product->loadMissing('translations');
    $translation = $product->translations->firstWhere('locale_id', $localeId);

    if ($translation === null) {
      return $base;
    }

    return [
      'title' => $translation->title !== null && $translation->title !== ''
        ? $translation->title
        : $base['title'],
      'description' => $translation->description !== null && $translation->description !== ''
        ? $translation->description
        : $base['description'],
    ];
  }

  public function title(CommerceProduct $product, ?string $localeCode): string
  {
    return $this->localize($product, $localeCode)['title'];
  }
}
