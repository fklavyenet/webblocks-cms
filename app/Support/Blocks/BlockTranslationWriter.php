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

        if ($family === 'contact_form') {
            $settings = $this->decodeSettings($data['settings'] ?? null);

            unset($settings['submit_label'], $settings['success_message']);

            $data['settings'] = $settings === []
                ? null
                : json_encode($settings, JSON_UNESCAPED_SLASHES);
        }

        if ($locale->id === $defaultLocale->id || $isCreating) {
            return $data;
        }

        foreach ($this->canonicalBlockFieldsForFamily($family) as $field) {
            unset($data[$field]);
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
        $isDefaultLocaleEdit = $locale->id === $defaultLocale->id;

        if ($isDefaultLocaleEdit || $duplicateDefaultOnCreate) {
            $this->writeTranslation($block, $family, $defaultLocale->id, $defaultPayload);
        } else {
            $this->ensureDefaultTranslation($block, $family, $defaultLocale->id);
        }

        if (! $isDefaultLocaleEdit) {
            $this->writeTranslation($block, $family, $locale->id, $localizedPayload);
        }
    }

    private function ensureDefaultTranslation(Block $block, string $family, int $defaultLocaleId): void
    {
        $relation = match ($family) {
            'text' => $block->textTranslations(),
            'button' => $block->buttonTranslations(),
            'image' => $block->imageTranslations(),
            'contact_form' => $block->contactFormTranslations(),
        };

        if ($relation->where('locale_id', $defaultLocaleId)->exists()) {
            return;
        }

        $relation->create(['locale_id' => $defaultLocaleId] + $this->translationPayload($family, [], $block, true));
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
                    ? $this->resolvedContactTranslationValue($data, $block, 'submit_label', 'Send message')
                    : $this->submittedContactTranslationValue($data, 'submit_label'),
                'success_message' => $preferCanonical
                    ? $this->resolvedContactTranslationValue($data, $block, 'success_message', config('contact.success_message'))
                    : $this->submittedContactTranslationValue($data, 'success_message'),
            ],
        };
    }

    private function resolvedContactTranslationValue(array $data, Block $block, string $field, string $default): string
    {
        $submitted = $this->submittedContactTranslationValue($data, $field);

        if ($submitted !== null) {
            return $submitted;
        }

        $submittedSettings = $this->decodeSettings($data['settings'] ?? null);
        $submittedSettingValue = trim((string) ($submittedSettings[$field] ?? ''));

        if ($submittedSettingValue !== '') {
            return $submittedSettingValue;
        }

        $translation = $block->contactFormTranslations()
            ->where('locale_id', $this->localeResolver->default()->id)
            ->first();

        $existing = trim((string) ($translation?->{$field} ?? ''));

        if ($existing !== '') {
            return $existing;
        }

        $rawSettings = $this->decodeSettings($block->getRawOriginal('settings'));

        return trim((string) ($rawSettings[$field] ?? '')) ?: $default;
    }

    private function submittedContactTranslationValue(array $data, string $field): ?string
    {
        $value = trim((string) ($data[$field] ?? ''));

        if ($value === '') {
            $submittedSettings = $this->decodeSettings($data['settings'] ?? null);
            $value = trim((string) ($submittedSettings[$field] ?? ''));
        }

        return $value !== '' ? $value : null;
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
