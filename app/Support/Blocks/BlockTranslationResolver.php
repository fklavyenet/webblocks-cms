<?php

namespace App\Support\Blocks;

use App\Models\Block;
use App\Models\Locale;
use App\Support\Locales\LocaleResolver;
use Illuminate\Support\Collection;

class BlockTranslationResolver
{
    public function __construct(
        private readonly BlockTranslationRegistry $registry,
        private readonly LocaleResolver $localeResolver,
    ) {}

    public function resolveCollection(Collection $blocks, Locale|string|null $locale = null): Collection
    {
        return $blocks->map(fn (Block $block) => $this->resolve($block, $locale));
    }

    public function resolve(Block $block, Locale|string|null $locale = null): Block
    {
        $requestedLocale = $this->resolveLocale($locale);
        $defaultLocale = $this->localeResolver->default();
        $resolved = clone $block;
        $family = $this->registry->familyFor($block);

        if ($resolved->relationLoaded('children')) {
            $resolved->setRelation('children', $this->resolveCollection($resolved->children, $requestedLocale));
        }

        if (! $family) {
            $resolved->setAttribute('translation_state', 'shared');
            $resolved->setAttribute('resolved_locale_code', $requestedLocale->code);

            return $resolved;
        }

        [$translation, $state, $resolvedLocale] = match ($family) {
            'text' => $this->resolveLoadedTranslation($resolved, 'textTranslations', $requestedLocale, $defaultLocale),
            'button' => $this->resolveLoadedTranslation($resolved, 'buttonTranslations', $requestedLocale, $defaultLocale),
            'image' => $this->resolveLoadedTranslation($resolved, 'imageTranslations', $requestedLocale, $defaultLocale),
            'contact_form' => $this->resolveLoadedTranslation($resolved, 'contactFormTranslations', $requestedLocale, $defaultLocale),
        };

        $this->applyResolvedFields($resolved, $family, $translation);

        $resolved->setAttribute('translation_state', $state);
        $resolved->setAttribute('resolved_locale_code', $resolvedLocale->code);

        return $resolved;
    }

    public function statusFor(Block $block, Locale|string|null $locale = null): array
    {
        $requestedLocale = $this->resolveLocale($locale);
        $defaultLocale = $this->localeResolver->default();
        $family = $this->registry->familyFor($block);

        if (! $family) {
            return [
                'state' => 'shared',
                'label' => 'Shared',
                'resolved_locale' => $requestedLocale,
            ];
        }

        [, $state, $resolvedLocale] = match ($family) {
            'text' => $this->resolveLoadedTranslation($block, 'textTranslations', $requestedLocale, $defaultLocale),
            'button' => $this->resolveLoadedTranslation($block, 'buttonTranslations', $requestedLocale, $defaultLocale),
            'image' => $this->resolveLoadedTranslation($block, 'imageTranslations', $requestedLocale, $defaultLocale),
            'contact_form' => $this->resolveLoadedTranslation($block, 'contactFormTranslations', $requestedLocale, $defaultLocale),
        };

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

    private function applyResolvedFields(Block $block, string $family, mixed $translation): void
    {
        if (! $translation) {
            return;
        }

        match ($family) {
            'text' => $this->applyAttributes($block, [
                'title' => $translation->title,
                'subtitle' => $translation->subtitle,
                'content' => $translation->content,
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

    private function applyContactFormFields(Block $block, mixed $translation): void
    {
        $settings = is_array($block->settings)
            ? $block->settings
            : (json_decode((string) $block->getRawOriginal('settings'), true) ?: []);

        $settings['submit_label'] = $translation->submit_label ?: ($settings['submit_label'] ?? 'Send message');
        $settings['success_message'] = $translation->success_message ?: ($settings['success_message'] ?? config('contact.success_message'));

        $this->applyAttributes($block, [
            'title' => $translation->title,
            'content' => $translation->content,
            'settings' => $settings,
        ]);
    }

    private function applyAttributes(Block $block, array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            $block->setAttribute($key, $value);
        }
    }

    private function resolveLocale(Locale|string|null $locale = null): Locale
    {
        if ($locale instanceof Locale) {
            return $locale;
        }

        if (is_string($locale) && $locale !== '') {
            return $this->localeResolver->resolve($locale) ?? $this->localeResolver->default();
        }

        return $this->localeResolver->current();
    }
}
