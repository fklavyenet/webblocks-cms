<?php

namespace WebBlocks\Cms\Support\Icons;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use WebBlocks\Cms\Models\IconCatalogItem;

class IconCatalog
{
  public function pickerOptions(string $context = 'content', ?string $selectedSlug = null, ?string $currentSlug = null): Collection
  {
    $selectedSlug = $this->normalizeSlug($selectedSlug);
    $currentSlug = $this->normalizeSlug($currentSlug);

    $options = $this->activeContextQuery($context)
      ->orderBy('sort_order')
      ->orderBy('label')
      ->get()
      ->unique('slug')
      ->values()
      ->map(fn (IconCatalogItem $icon) => [
        'slug' => $icon->slug,
        'label' => $icon->label,
      ]);

    if ($selectedSlug !== null && ! $options->contains(fn (array $option) => $option['slug'] === $selectedSlug)) {
      $options->prepend($this->syntheticOption($selectedSlug, $currentSlug));
    }

    return $options->values();
  }

  /**
   * Picker options split into the context's own icons and everything else.
   *
   * A context tag comes from the WebBlocks UI manifest and records where an
   * icon is used in the product's own chrome — controls, sorting, auth,
   * devices. Content authoring cares about what an icon depicts instead, and
   * the two rarely line up: `shield-check` is tagged security, `rocket`
   * navigation, and both are ordinary choices for a feature card. Filtering
   * content pickers by the tag left 11 of the manifest's 183 icons reachable.
   *
   * So the tag now sorts rather than restricts: its icons lead the list as
   * suggestions, and the rest of the active catalog follows.
   *
   * @return array{suggested: Collection<int, array{slug: string, label: string}>, all: Collection<int, array{slug: string, label: string}>}
   */
  public function groupedPickerOptions(string $context = 'content', ?string $selectedSlug = null, ?string $currentSlug = null): array
  {
    $suggested = $this->pickerOptions($context, $selectedSlug, $currentSlug);
    $suggestedSlugs = $suggested->pluck('slug')->all();

    $rest = IconCatalogItem::query()
      ->active()
      ->whereNotIn('slug', $suggestedSlugs)
      ->orderBy('label')
      ->get()
      ->unique('slug')
      ->values()
      ->map(fn (IconCatalogItem $icon) => [
        'slug' => $icon->slug,
        'label' => $icon->label,
      ]);

    return ['suggested' => $suggested, 'all' => $rest->values()];
  }

  public function navigationPickerOptions(?string $selectedSlug = null, ?string $currentSlug = null): Collection
  {
    return $this->pickerOptions('navigation', $selectedSlug, $currentSlug);
  }

  /**
   * Whether a slug names an icon the catalog currently has active.
   *
   * This is the check content blocks validate against. Navigation keeps its
   * narrower, context-bound rule: those icons sit in menus and sidebars, where
   * a curated set is the point.
   */
  public function isActiveSelection(?string $slug): bool
  {
    $slug = $this->normalizeSlug($slug);

    return $slug === null || $this->activeIconQuery()->where('slug', $slug)->exists();
  }

  /**
   * The slug when the catalog has it active, otherwise null.
   *
   * Rendering used to apply the context filter too, so an icon outside the
   * context vanished from the page with nothing said anywhere. An icon the
   * catalog has is an icon the page shows.
   */
  public function activeIconSlug(?string $slug): ?string
  {
    $slug = $this->normalizeSlug($slug);

    return $slug !== null && $this->isActiveSelection($slug) ? $slug : null;
  }

  public function isValidNavigationSelection(?string $slug, ?string $currentSlug = null): bool
  {
    $slug = $this->normalizeSlug($slug);
    $currentSlug = $this->normalizeSlug($currentSlug);

    if ($slug === null) {
      return true;
    }

    if ($currentSlug !== null && $slug === $currentSlug) {
      return true;
    }

    return $this->navigationIconsQuery()->where('slug', $slug)->exists();
  }

  public function normalizeSlug(?string $slug): ?string
  {
    return IconCatalogItem::normalizeSlug($slug);
  }

  private function navigationIconsQuery(): Builder
  {
    return $this->activeContextQuery('navigation');
  }

  private function activeIconQuery(): Builder
  {
    return IconCatalogItem::query()->active();
  }

  private function activeContextQuery(string $context): Builder
  {
    $context = IconCatalogItem::normalizeTag($context) ?? 'content';

    return IconCatalogItem::query()
      ->active()
      ->tagged($context);
  }

  private function syntheticOption(string $slug, ?string $currentSlug): array
  {
    $icon = IconCatalogItem::query()
      ->where('slug', $slug)
      ->orderByDesc('is_active')
      ->orderBy('sort_order')
      ->first();

    if ($icon) {
      $status = [];

      if (! $icon->is_active) {
        $status[] = 'inactive';
      }

      if (! $icon->isTagged('navigation')) {
        $status[] = 'not for navigation';
      }

      $prefix = $currentSlug !== null && $slug === $currentSlug ? 'Current' : 'Selected';

      return [
        'slug' => $slug,
        'label' => $prefix.': '.$icon->label.' ('.implode(', ', $status === [] ? ['unavailable'] : $status).')',
      ];
    }

    $prefix = $currentSlug !== null && $slug === $currentSlug ? 'Current' : 'Selected';

    return [
      'slug' => $slug,
      'label' => $prefix.': '.Str::of($slug)->replace('-', ' ')->title()->toString().' (unlisted)',
    ];
  }
}
