<?php

namespace WebBlocks\Cms\Support\Blocks;

use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;

/**
 * Shared owner of Hero/CTA managed call-to-action buttons.
 *
 * Hero and CTA do not accept free-form button children: their actions are
 * "managed" child button blocks derived from primary/secondary CTA fields. The
 * admin editor and the Internal Content API both go through this class so a
 * CTA created by an AI tool stays editable in the normal block editor.
 */
class ManagedCtaSynchronizer
{
  private const SUPPORTED_PARENT_TYPES = ['hero', 'cta'];

  private const BUTTON_TYPES = ['button_link', 'button'];

  public function __construct(
    private readonly BlockPayloadWriter $blockPayloadWriter,
  ) {}

  public function supports(?string $parentSlug): bool
  {
    return in_array((string) $parentSlug, self::SUPPORTED_PARENT_TYPES, true);
  }

  /**
   * Normalizes an API block payload's primary_cta/secondary_cta objects into
   * the managed CTA shape. Returns an empty array when the block type does not
   * support managed CTAs or nothing was supplied.
   *
   * @param  array<string, mixed>  $block
   * @param  array<int, array<string, mixed>>  $errors
   * @return array<int, array<string, mixed>>
   */
  public function normalizeApiPayload(array $block, ?string $parentSlug, string $path, array &$errors): array
  {
    $supplied = array_intersect_key($block, ['primary_cta' => true, 'secondary_cta' => true]);

    if ($supplied === []) {
      return [];
    }

    if (! $this->supports($parentSlug)) {
      $errors[] = [
        'path' => $path.'.primary_cta',
        'message' => 'Managed CTA fields are only supported by hero and cta blocks.',
      ];

      return [];
    }

    $normalized = [];

    foreach ([['primary', 'primary_cta'], ['secondary', 'secondary_cta']] as $index => [$key, $field]) {
      if (! array_key_exists($field, $block)) {
        $normalized[] = ['key' => $key, 'label' => null, 'url' => null, 'variant' => $key, 'sort_order' => $index];

        continue;
      }

      $cta = $block[$field];

      if ($cta === null) {
        $normalized[] = ['key' => $key, 'label' => null, 'url' => null, 'variant' => $key, 'sort_order' => $index];

        continue;
      }

      if (! is_array($cta)) {
        $errors[] = ['path' => $path.'.'.$field, 'message' => 'Managed CTA must be an object with label and url, or null to clear it.'];

        continue;
      }

      $label = trim((string) ($cta['label'] ?? ''));
      $url = trim((string) ($cta['url'] ?? ''));

      if ($label === '') {
        $errors[] = ['path' => $path.'.'.$field.'.label', 'message' => 'Managed CTA label is required.'];
      }

      if ($url === '') {
        $errors[] = ['path' => $path.'.'.$field.'.url', 'message' => 'Managed CTA url is required.'];
      } elseif (! $this->isSafeUrl($url)) {
        $errors[] = ['path' => $path.'.'.$field.'.url', 'message' => 'Managed CTA url must be a safe internal path or http(s) URL.'];
      }

      $normalized[] = [
        'key' => $key,
        'label' => $label !== '' ? $label : null,
        'url' => $url !== '' ? $url : null,
        'variant' => $key,
        'sort_order' => $index,
      ];
    }

    return $normalized;
  }

  /**
   * Creates, updates, or removes the managed child button blocks for a Hero or
   * CTA parent. Moved verbatim from the admin block editor so both surfaces
   * share one behavior.
   *
   * @param  array<int, array<string, mixed>>  $managedCtas
   */
  public function sync(Block $block, array $managedCtas, ?string $localeCode = null): void
  {
    if ($managedCtas === [] || ! $this->supports($block->typeSlug())) {
      return;
    }

    $buttonType = BlockType::query()
      ->whereIn('slug', self::BUTTON_TYPES)
      ->orderByRaw("CASE WHEN slug = 'button_link' THEN 0 ELSE 1 END")
      ->first();

    if (! $buttonType) {
      return;
    }

    $resolvedLocale = $localeCode
      ? Locale::query()->whereRaw('LOWER(code) = ?', [strtolower($localeCode)])->first()
      : null;
    $isDefaultLocaleEdit = ! $resolvedLocale || $resolvedLocale->is_default;

    $managedButtons = $block->children()
      ->whereIn('type', self::BUTTON_TYPES)
      ->orderBy('sort_order')
      ->limit(2)
      ->get()
      ->values();

    foreach ($managedCtas as $index => $cta) {
      $existing = $managedButtons->get($index);
      $hasSharedPayload = filled($cta['url']) || ($isDefaultLocaleEdit && filled($cta['label']));
      $hasTranslatedPayload = ! $isDefaultLocaleEdit && filled($cta['label']);

      if (! $existing && ! $hasSharedPayload && ! $hasTranslatedPayload) {
        continue;
      }

      if (! $existing && ! $isDefaultLocaleEdit) {
        continue;
      }

      if ($existing && blank($cta['label']) && blank($cta['url']) && $isDefaultLocaleEdit) {
        $existing->delete();

        continue;
      }

      if ($existing && blank($cta['label']) && ! $isDefaultLocaleEdit) {
        continue;
      }

      $resolvedUrl = $isDefaultLocaleEdit
        ? $cta['url']
        : ($existing?->buttonLinkUrl() ?: $existing?->url);

      $payload = [
        'page_id' => $block->page_id,
        'parent_id' => $block->id,
        'block_type_id' => $buttonType->id,
        'type' => $buttonType->slug,
        'source_type' => $buttonType->source_type ?? 'static',
        'slot_type_id' => $block->slot_type_id,
        'slot' => $block->slot,
        'sort_order' => $cta['sort_order'],
        'title' => $cta['label'],
        'url' => $resolvedUrl,
        'subtitle' => $existing?->subtitle ?: '_self',
        'variant' => $cta['variant'],
        'status' => $existing?->status ?: 'published',
        'is_system' => false,
      ];

      // button_link reads its link from settings, not from the legacy button
      // columns, so a managed CTA must be written in the shape its own renderer
      // and admin form expect. Otherwise the action renders without a URL.
      if ($buttonType->slug === 'button_link') {
        $existingSettings = is_array($existing?->settings) ? $existing->settings : [];
        $payload['settings'] = json_encode([
          'url' => $resolvedUrl,
          'target' => $existingSettings['target'] ?? '_self',
        ], JSON_UNESCAPED_SLASHES);
      }

      $this->blockPayloadWriter->save($existing ?? new Block, $block->page, $payload, $localeCode);
    }
  }

  private function isSafeUrl(string $url): bool
  {
    if (str_starts_with($url, '/')) {
      return ! str_starts_with($url, '//');
    }

    return (bool) preg_match('#^https?://#i', $url);
  }
}
