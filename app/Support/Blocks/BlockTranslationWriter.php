<?php

namespace App\Support\Blocks;

use App\Models\Block;
use App\Models\Locale;
use App\Models\Page;
use App\Support\Locales\LocaleResolver;

class BlockTranslationWriter
{
    public function __construct(
        private readonly BlockTranslationRegistry $registry,
        private readonly LocaleResolver $localeResolver,
    ) {}

    public function canonicalPayload(array $data, ?Block $block, Page $page, ?string $localeCode, bool $isCreating = false): array
    {
        $blockTypeSlug = $data['type'] ?? $block?->typeSlug();
        $family = $this->registry->familyFor($blockTypeSlug);
        $locale = $this->resolveLocale($localeCode);
        $defaultLocale = $this->localeResolver->default();

        if (! $family) {
            return $data;
        }

        if ($locale->id === $defaultLocale->id || $isCreating) {
            return $data;
        }

        foreach ($this->canonicalBlockFieldsForFamily($family) as $field) {
            unset($data[$field]);
        }

        if ($family === 'contact_form') {
            $settings = $this->decodeSettings($data['settings'] ?? null);

            unset($settings['submit_label'], $settings['success_message']);

            $data['settings'] = $settings === []
                ? null
                : json_encode($settings, JSON_UNESCAPED_SLASHES);
        }

        return $data;
    }

    public function sync(Block $block, array $data, ?string $localeCode, bool $duplicateDefaultOnCreate = false): void
    {
        $family = $this->registry->familyFor($block);

        if (! $family) {
            return;
        }

        $locale = $this->resolveLocale($localeCode);
        $defaultLocale = $this->localeResolver->default();

        $defaultPayload = $this->translationPayload($family, $data, $block, true);
        $localizedPayload = $this->translationPayload($family, $data, $block, $locale->id === $defaultLocale->id || $duplicateDefaultOnCreate);

        $this->writeTranslation($block, $family, $defaultLocale->id, $defaultPayload);

        if ($locale->id !== $defaultLocale->id) {
            $this->writeTranslation($block, $family, $locale->id, $localizedPayload);
        }
    }

    private function writeTranslation(Block $block, string $family, int $localeId, array $payload): void
    {
        match ($family) {
            'text' => $block->textTranslations()->updateOrCreate(['locale_id' => $localeId], $payload),
            'button' => $block->buttonTranslations()->updateOrCreate(['locale_id' => $localeId], $payload),
            'image' => $block->imageTranslations()->updateOrCreate(['locale_id' => $localeId], $payload),
            'contact_form' => $block->contactFormTranslations()->updateOrCreate(['locale_id' => $localeId], $payload),
        };
    }

    private function translationPayload(string $family, array $data, Block $block, bool $preferCanonical): array
    {
        $rawSettings = $this->decodeSettings($block->getRawOriginal('settings'));
        $submittedSettings = $this->decodeSettings($data['settings'] ?? null);

        return match ($family) {
            'text' => [
                'title' => $preferCanonical ? ($data['title'] ?? $block->getRawOriginal('title')) : ($data['title'] ?? null),
                'subtitle' => $preferCanonical ? ($data['subtitle'] ?? $block->getRawOriginal('subtitle')) : ($data['subtitle'] ?? null),
                'content' => $preferCanonical ? ($data['content'] ?? $block->getRawOriginal('content')) : ($data['content'] ?? null),
            ],
            'button' => [
                'title' => $preferCanonical ? ($data['title'] ?? $block->getRawOriginal('title') ?: 'Open link') : ($data['title'] ?? 'Open link'),
            ],
            'image' => [
                'caption' => $preferCanonical ? ($data['title'] ?? $block->getRawOriginal('title')) : ($data['title'] ?? null),
                'alt_text' => $preferCanonical ? ($data['subtitle'] ?? $block->getRawOriginal('subtitle')) : ($data['subtitle'] ?? null),
            ],
            'contact_form' => [
                'title' => $preferCanonical ? ($data['title'] ?? $block->getRawOriginal('title')) : ($data['title'] ?? null),
                'content' => $preferCanonical ? ($data['content'] ?? $block->getRawOriginal('content')) : ($data['content'] ?? null),
                'submit_label' => $preferCanonical
                    ? ($submittedSettings['submit_label'] ?? $rawSettings['submit_label'] ?? 'Send message')
                    : (trim((string) ($submittedSettings['submit_label'] ?? '')) ?: null),
                'success_message' => $preferCanonical
                    ? ($submittedSettings['success_message'] ?? $rawSettings['success_message'] ?? config('contact.success_message'))
                    : (trim((string) ($submittedSettings['success_message'] ?? '')) ?: null),
            ],
        };
    }

    private function canonicalBlockFieldsForFamily(string $family): array
    {
        return match ($family) {
            'text' => ['title', 'subtitle', 'content'],
            'button' => ['title'],
            'image' => ['title', 'subtitle'],
            'contact_form' => ['title', 'content'],
            default => [],
        };
    }

    private function resolveLocale(?string $localeCode): Locale
    {
        if ($localeCode) {
            return $this->localeResolver->resolve($localeCode) ?? $this->localeResolver->default();
        }

        return $this->localeResolver->default();
    }

    private function decodeSettings(mixed $settings): array
    {
        if (is_array($settings)) {
            return $settings;
        }

        if (! is_string($settings) || trim($settings) === '') {
            return [];
        }

        $decoded = json_decode($settings, true);

        return is_array($decoded) ? $decoded : [];
    }
}
