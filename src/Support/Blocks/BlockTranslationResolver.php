<?php

namespace WebBlocks\Cms\Support\Blocks;

use Illuminate\Support\Collection;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Locales\LocaleResolver;
use WebBlocks\Cms\Support\Sites\SiteTokenRenderer;

class BlockTranslationResolver
{
  public function __construct(
    private readonly BlockTranslationRegistry $registry,
    private readonly LocaleResolver $localeResolver,
    private readonly SiteTokenRenderer $siteTokenRenderer,
  ) {}

  public function resolveCollection(Collection $blocks, Locale|string|null $locale = null, ?Site $site = null): Collection
  {
    return $blocks->map(fn (Block $block) => $this->resolve($block, $locale, $site));
  }

  public function resolve(Block $block, Locale|string|null $locale = null, ?Site $site = null): Block
  {
    $requestedLocale = $this->resolveLocale($locale);
    $defaultLocale = $this->localeResolver->default();
    $resolved = clone $block;
    $family = $this->registry->familyFor($block);

    if ($resolved->relationLoaded('children')) {
      $resolved->setRelation('children', $this->resolveCollection($resolved->children, $requestedLocale, $site));
    }

    if (! $family && ! $this->registry->supportsImageTranslations($block)) {
      $this->applySiteVariables($resolved, $site);
      $resolved->setAttribute('translation_state', 'shared');
      $resolved->setAttribute('resolved_locale_code', $requestedLocale->code);

      return $resolved;
    }

    $translation = null;
    $state = 'shared';
    $resolvedLocale = $requestedLocale;

    if ($family) {
      [$translation, $state, $resolvedLocale] = match ($family) {
        BlockTranslationRegistry::PLUGIN_FAMILY => $this->resolvePluginTranslation($resolved, $requestedLocale, $defaultLocale),
        'text' => $this->resolveLoadedTranslation($resolved, 'textTranslations', $requestedLocale, $defaultLocale),
        'button' => $this->resolveLoadedTranslation($resolved, 'buttonTranslations', $requestedLocale, $defaultLocale),
        'image' => $this->resolveLoadedTranslation($resolved, 'imageTranslations', $requestedLocale, $defaultLocale),
        'contact_form' => $this->resolveLoadedTranslation($resolved, 'contactFormTranslations', $requestedLocale, $defaultLocale),
      };

      $this->applyResolvedFields($resolved, $family, $translation);
    }

    if ($this->registry->supportsImageTranslations($resolved)) {
      [$imageTranslation, $imageState, $imageResolvedLocale] = $this->resolveLoadedTranslation($resolved, 'imageTranslations', $requestedLocale, $defaultLocale);
      $this->applyResolvedImageFields($resolved, $imageTranslation);

      if (! $family) {
        $state = $imageState;
        $resolvedLocale = $imageResolvedLocale;
      }
    }

    $this->applySiteVariables($resolved, $site);

    $resolved->setAttribute('translation_state', $state);
    $resolved->setAttribute('resolved_locale_code', $resolvedLocale->code);

    return $resolved;
  }

  public function statusFor(Block $block, Locale|string|null $locale = null): array
  {
    $requestedLocale = $this->resolveLocale($locale);
    $defaultLocale = $this->localeResolver->default();
    $family = $this->registry->familyFor($block);

    if (! $family && ! $this->registry->supportsImageTranslations($block)) {
      return [
        'state' => 'shared',
        'label' => 'Shared',
        'resolved_locale' => $requestedLocale,
      ];
    }

    if ($family) {
      [, $state, $resolvedLocale] = match ($family) {
        BlockTranslationRegistry::PLUGIN_FAMILY => $this->resolvePluginTranslation($block, $requestedLocale, $defaultLocale),
        'text' => $this->resolveLoadedTranslation($block, 'textTranslations', $requestedLocale, $defaultLocale),
        'button' => $this->resolveLoadedTranslation($block, 'buttonTranslations', $requestedLocale, $defaultLocale),
        'image' => $this->resolveLoadedTranslation($block, 'imageTranslations', $requestedLocale, $defaultLocale),
        'contact_form' => $this->resolveLoadedTranslation($block, 'contactFormTranslations', $requestedLocale, $defaultLocale),
      };
    } else {
      [, $state, $resolvedLocale] = $this->resolveLoadedTranslation($block, 'imageTranslations', $requestedLocale, $defaultLocale);
    }

    return [
      'state' => $state,
      'label' => match ($state) {
        'translated' => 'Translated',
        'fallback' => 'Fallback',
        'missing' => 'Missing',
        default => 'Shared',
      },
      'resolved_locale' => $resolvedLocale,
    ];
  }

  private function resolveLoadedTranslation(Block $block, string $relation, Locale $requestedLocale, Locale $defaultLocale): array
  {
    $translations = $block->relationLoaded($relation)
      ? $block->getRelation($relation)
      : $block->{$relation}()->get();

    $requested = $translations->firstWhere('locale_id', $requestedLocale->id);

    if ($requested) {
      return [$requested, 'translated', $requestedLocale];
    }

    $default = $translations->firstWhere('locale_id', $defaultLocale->id);

    if ($default) {
      return [$default, $requestedLocale->id === $defaultLocale->id ? 'translated' : 'fallback', $defaultLocale];
    }

    return [null, 'missing', $defaultLocale];
  }

  /**
   * The plugin family's rows for a locale, with the same fallback the others get.
   *
   * Returned as a field/value map rather than a model, because a plugin block's copy
   * is several rows sharing a locale where every other family is one row. A locale
   * with any row at all counts as translated: a plugin declaring five fields where
   * the operator filled two has still been translated, and calling that `missing`
   * would report work that was done as work that was not.
   *
   * @return array{0: array<string, string|null>|null, 1: string, 2: Locale}
   */
  private function resolvePluginTranslation(Block $block, Locale $requestedLocale, Locale $defaultLocale): array
  {
    $rows = $block->relationLoaded('pluginTranslations')
      ? $block->getRelation('pluginTranslations')
      : $block->pluginTranslations()->get();

    $forLocale = static fn (int $localeId): array => $rows
      ->where('locale_id', $localeId)
      ->pluck('value', 'field')
      ->all();

    $requested = $forLocale($requestedLocale->id);

    if ($requested !== []) {
      return [$requested, 'translated', $requestedLocale];
    }

    $fallback = $forLocale($defaultLocale->id);

    if ($fallback !== []) {
      return [
        $fallback,
        $requestedLocale->id === $defaultLocale->id ? 'translated' : 'fallback',
        $defaultLocale,
      ];
    }

    return [null, 'missing', $requestedLocale];
  }

  /**
   * Overlay translated copy onto the block's settings.
   *
   * A plugin block reads its copy through `$block->setting()`, which decodes the
   * settings column — so a translation has to arrive there rather than on a block
   * attribute. Only fields with a value are overlaid: a blank translation means the
   * operator has not written that one yet, and blanking the default would show a
   * visitor an empty heading rather than the original.
   *
   * @param  array<string, string|null>|null  $translation
   */
  private function applyResolvedPluginFields(Block $block, mixed $translation): void
  {
    if (! is_array($translation)) {
      return;
    }

    $settings = $block->decodedSettings();
    $settings = is_array($settings) ? $settings : [];

    foreach ($translation as $field => $value) {
      if ($value !== null && $value !== '') {
        $settings[$field] = $value;
      }
    }

    $block->setAttribute('settings', $settings === [] ? null : json_encode($settings, JSON_UNESCAPED_SLASHES));
  }

  private function applyResolvedFields(Block $block, string $family, mixed $translation): void
  {
    if (! $translation) {
      return;
    }

    if ($family === BlockTranslationRegistry::PLUGIN_FAMILY) {
      $this->applyResolvedPluginFields($block, $translation);

      return;
    }

    match ($family) {
      'text' => $this->applyAttributes($block, [
        'title' => $translation->title,
        'eyebrow' => $translation->eyebrow,
        'subtitle' => $translation->subtitle,
        'content' => $translation->content,
        'meta' => $translation->meta,
      ]),
      'button' => $this->applyAttributes($block, [
        'title' => $translation->title,
      ]),
      'image' => $this->applyAttributes($block, [
        'title' => $translation->caption,
        'subtitle' => $translation->alt_text,
      ]),
      'contact_form' => $this->applyContactFormFields($block, $translation),
    };
  }

  private function applyResolvedImageFields(Block $block, mixed $translation): void
  {
    if (! $translation) {
      return;
    }

    $this->applyResolvedFields($block, 'image', $translation);
  }

  private function applyContactFormFields(Block $block, mixed $translation): void
  {
    $settings = is_array($block->settings)
      ? $block->settings
      : (json_decode((string) $block->getRawOriginal('settings'), true) ?: []);

    unset($settings['submit_label'], $settings['success_message'], $settings['consent_label']);

    $this->applyAttributes($block, [
      'title' => $translation->title,
      'content' => $translation->content,
      'submit_label' => $translation->submit_label ?: 'Send message',
      'success_message' => $translation->success_message ?: config('contact.success_message'),
      'consent_label' => $translation->consent_label ?: null,
      'settings' => $settings,
    ]);
  }

  private function applyAttributes(Block $block, array $attributes): void
  {
    foreach ($attributes as $key => $value) {
      $block->setAttribute($key, $value);
    }
  }

  private function applySiteVariables(Block $block, ?Site $site): void
  {
    if (! $site) {
      return;
    }

    $slug = $block->typeSlug();

    foreach (['title', 'eyebrow', 'subtitle', 'content', 'meta', 'submit_label', 'success_message'] as $field) {
      $value = $block->getAttribute($field);

      if (! is_string($value) || $value === '') {
        continue;
      }

      $block->setAttribute(
        $field,
        $this->siteTokenRenderer->render(
          $value,
          $site,
          escapeReplacement: $this->shouldEscapeReplacement($slug, $field),
        ),
      );
    }
  }

  private function shouldEscapeReplacement(?string $slug, string $field): bool
  {
    return $field === 'content' && in_array($slug, ['html', 'rich-text'], true);
  }

  private function resolveLocale(Locale|string|null $locale = null): Locale
  {
    if ($locale instanceof Locale) {
      return $locale->is_enabled ? $locale : $this->localeResolver->default();
    }

    if (is_string($locale) && $locale !== '') {
      return $this->localeResolver->enabled($locale) ?? $this->localeResolver->default();
    }

    return $this->localeResolver->current();
  }
}
