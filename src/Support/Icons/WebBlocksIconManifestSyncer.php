<?php

namespace WebBlocks\Cms\Support\Icons;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use WebBlocks\Cms\Models\IconCatalogItem;
use WebBlocks\Cms\Support\WebBlocks;

class WebBlocksIconManifestSyncer
{
  public const DEFAULT_MANIFEST = WebBlocks::ICONS_MANIFEST_URL;

  /**
   * The manifest shipped with the package, for the pinned UI version.
   *
   * Install and catalog repair read this rather than the CDN. The icon catalog
   * is not optional content — every icon field in the admin is empty without
   * it — so filling it cannot depend on outbound network the host may not
   * have. The remote manifest stays the explicit action, for pulling a set
   * newer than the pinned one.
   */
  public static function bundledManifestPath(): string
  {
    return dirname(__DIR__, 3).'/database/content/icons/webblocks-ui-'.WebBlocks::uiVersion().'.json';
  }

  /**
   * What install and catalog repair read: the bundled manifest, falling back
   * to the pinned remote one.
   *
   * Every real distribution carries the file — the release flow refuses to
   * build without it — so the fallback is for a hand-assembled checkout, not
   * the normal path.
   */
  public static function installManifestSource(): string
  {
    $bundled = self::bundledManifestPath();

    return is_file($bundled) && is_readable($bundled) ? $bundled : self::DEFAULT_MANIFEST;
  }

  /**
   * @return array<int, array<string, mixed>>
   */
  public function readInstallManifest(): array
  {
    return $this->readManifest(self::installManifestSource());
  }

  public function sync(?string $manifest = null, bool $deactivateMissing = false): array
  {
    $manifest = trim((string) ($manifest ?: self::DEFAULT_MANIFEST));

    if ($manifest === self::DEFAULT_MANIFEST) {
      $manifest = self::bundledManifestPath();
    }

    $entries = $this->readManifest($manifest);
    $syncedAt = now();
    $seenSlugs = [];
    $created = 0;
    $updated = 0;
    $unchanged = 0;
    $sources = [];

    foreach ($entries as $entry) {
      $attributes = $this->normalizeEntry($entry);
      $sources[] = $attributes['source'];
      $seenSlugs[$attributes['source']][] = $attributes['slug'];

      $icon = IconCatalogItem::query()->firstOrNew([
        'source' => $attributes['source'],
        'slug' => $attributes['slug'],
      ]);

      $icon->fill([
        'label' => $attributes['label'],
        'css_class' => $attributes['css_class'],
        'categories' => $attributes['categories'],
        'contexts' => $attributes['contexts'],
        'keywords' => $attributes['keywords'],
        'synced_at' => $syncedAt,
      ]);

      if (! $icon->exists) {
        $icon->save();
        $created++;

        continue;
      }

      if ($icon->isDirty()) {
        $icon->save();
        $updated++;

        continue;
      }

      $icon->touch();
      $icon->forceFill(['synced_at' => $syncedAt])->saveQuietly();
      $unchanged++;
    }

    $deactivated = 0;

    if ($deactivateMissing) {
      foreach (collect($sources)->unique()->values() as $source) {
        $deactivated += IconCatalogItem::query()
          ->where('source', $source)
          ->whereNotIn('slug', $seenSlugs[$source] ?? [])
          ->where('is_active', true)
          ->update(['is_active' => false]);
      }
    }

    return [
      'manifest' => $manifest,
      'created' => $created,
      'updated' => $updated,
      'unchanged' => $unchanged,
      'deactivated' => $deactivated,
    ];
  }

  private function readManifest(string $manifest): array
  {
    $contents = $this->isUrl($manifest)
          ? $this->readRemoteManifest($manifest)
          : $this->readLocalManifest($manifest);

    $decoded = json_decode($contents, true);

    if (! is_array($decoded)) {
      throw new RuntimeException('The icon manifest must decode to a JSON array.');
    }

    return array_values($decoded);
  }

  private function normalizeEntry(mixed $entry): array
  {
    if (! is_array($entry)) {
      throw new RuntimeException('Every icon manifest entry must be an object-like JSON item.');
    }

    $slug = IconCatalogItem::normalizeSlug(Arr::get($entry, 'slug'));

    if ($slug === null) {
      throw new RuntimeException('Manifest entries must include a valid icon slug.');
    }

    $source = trim(Str::lower((string) Arr::get($entry, 'source', 'webblocks-ui'))) ?: 'webblocks-ui';
    $label = trim((string) Arr::get($entry, 'label')) ?: Str::of($slug)->replace('-', ' ')->title()->toString();
    $cssClass = IconCatalogItem::normalizeCssClass((string) Arr::get($entry, 'css_class', ''), $slug);
    $categories = IconCatalogItem::normalizeTags(Arr::get($entry, 'categories'));
    $contexts = IconCatalogItem::normalizeTags(Arr::get($entry, 'contexts'));
    $keywords = IconCatalogItem::normalizeKeywords(Arr::get($entry, 'keywords'));

    return [
      'source' => $source,
      'slug' => $slug,
      'label' => $label,
      'css_class' => $cssClass,
      'categories' => $categories,
      'contexts' => $contexts,
      'keywords' => $keywords,
    ];
  }

  private function isUrl(string $manifest): bool
  {
    return Str::startsWith(Str::lower($manifest), ['http://', 'https://']);
  }

  private function readRemoteManifest(string $manifest): string
  {
    $response = Http::timeout(30)->get($manifest);

    if (! $response->successful()) {
      throw new RuntimeException('Failed to download the icon manifest from ['.$manifest.'].');
    }

    return $response->body();
  }

  private function readLocalManifest(string $manifest): string
  {
    if (! is_file($manifest) || ! is_readable($manifest)) {
      throw new RuntimeException('Icon manifest file not found or not readable at ['.$manifest.'].');
    }

    $contents = file_get_contents($manifest);

    if ($contents === false) {
      throw new RuntimeException('Failed to read the icon manifest file at ['.$manifest.'].');
    }

    return $contents;
  }
}
