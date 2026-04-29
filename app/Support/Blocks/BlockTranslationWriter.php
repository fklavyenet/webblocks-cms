<?php

namespace App\Support\Blocks;

use App\Models\Block;
use App\Models\Locale;
use App\Models\Page;
use App\Support\Locales\LocaleResolver;
use Illuminate\Support\Facades\DB;

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

        foreach ($this->canonicalBlockFieldsForFamily($family) as $field) {
            $data[$field] = null;
        }

        return $data;
    }

    public function sync(Block $block, array $data, ?string $localeCode, bool $duplicateDefaultOnCreate = false, ?Block $translationSourceBlock = null): void
    {
        $family = $this->registry->familyFor($block);

        if (! $family) {
            return;
        }

        $locale = $this->resolveLocale($localeCode);
        $defaultLocale = $this->localeResolver->default();
        $translationSourceBlock ??= $block;

        $isDefaultLocaleEdit = $locale->id === $defaultLocale->id;

        if ($isDefaultLocaleEdit || $duplicateDefaultOnCreate) {
            $this->writeTranslation($block, $family, $defaultLocale->id, $this->translationPayload($family, $data, $translationSourceBlock, $defaultLocale->id));
        } else {
            $this->ensureDefaultTranslation($block, $family, $defaultLocale->id, $translationSourceBlock);
        }

        if (! $isDefaultLocaleEdit) {
            $this->writeTranslation($block, $family, $locale->id, $this->translationPayload($family, $data, $translationSourceBlock, $locale->id));
        }
    }

    public function normalizeCanonicalStorage(Block $block): void
    {
        $family = $this->registry->familyFor($block);

        if (! $family) {
            return;
        }

        $defaultLocaleId = $this->localeResolver->default()->id;

        $this->ensureDefaultTranslation($block, $family, $defaultLocaleId, $block);

        $updates = array_fill_keys($this->canonicalBlockFieldsForFamily($family), null);

        if ($family === 'contact_form') {
            $settings = $this->decodeSettings($block->getRawOriginal('settings'));

            unset($settings['submit_label'], $settings['success_message']);

            $updates['settings'] = $settings === []
                ? null
                : json_encode($settings, JSON_UNESCAPED_SLASHES);
        }

        DB::table('blocks')->where('id', $block->id)->update($updates);
    }

    private function ensureDefaultTranslation(Block $block, string $family, int $defaultLocaleId, ?Block $translationSourceBlock = null): void
    {
        $relation = match ($family) {
            'text' => $block->textTranslations(),
            'button' => $block->buttonTranslations(),
            'image' => $block->imageTranslations(),
            'contact_form' => $block->contactFormTranslations(),
        };

        $translationSourceBlock ??= $block;

        if ($relation->where('locale_id', $defaultLocaleId)->exists()) {
            return;
        }

        $relation->create(['locale_id' => $defaultLocaleId] + $this->translationPayload($family, [], $translationSourceBlock, $defaultLocaleId));
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

    private function translationPayload(string $family, array $data, Block $block, int $localeId): array
    {
        return match ($family) {
            'text' => [
                'title' => array_key_exists('title', $data) ? $data['title'] : $this->existingTranslationValue($block, 'textTranslations', $localeId, 'title', $block->getRawOriginal('title')),
                'eyebrow' => array_key_exists('eyebrow', $data) ? $data['eyebrow'] : $this->existingTranslationValue($block, 'textTranslations', $localeId, 'eyebrow'),
                'subtitle' => array_key_exists('subtitle', $data) ? $data['subtitle'] : $this->existingTranslationValue($block, 'textTranslations', $localeId, 'subtitle', $block->getRawOriginal('subtitle')),
                'content' => array_key_exists('content', $data) ? $data['content'] : $this->existingTranslationValue($block, 'textTranslations', $localeId, 'content', $block->getRawOriginal('content')),
                'meta' => array_key_exists('meta', $data) ? $data['meta'] : $this->existingTranslationValue($block, 'textTranslations', $localeId, 'meta', $block->getRawOriginal('meta')),
            ],
            'button' => [
                'title' => $this->submittedString($data, 'title')
                    ?? $this->existingTranslationValue($block, 'buttonTranslations', $localeId, 'title', $block->getRawOriginal('title'))
                    ?? 'Open link',
            ],
            'image' => [
                'caption' => array_key_exists('title', $data) ? $data['title'] : $this->existingTranslationValue($block, 'imageTranslations', $localeId, 'caption', $block->getRawOriginal('title')),
                'alt_text' => array_key_exists('subtitle', $data) ? $data['subtitle'] : $this->existingTranslationValue($block, 'imageTranslations', $localeId, 'alt_text', $block->getRawOriginal('subtitle')),
            ],
            'contact_form' => [
                'title' => array_key_exists('title', $data) ? $data['title'] : $this->existingTranslationValue($block, 'contactFormTranslations', $localeId, 'title', $block->getRawOriginal('title')),
                'content' => array_key_exists('content', $data) ? $data['content'] : $this->existingTranslationValue($block, 'contactFormTranslations', $localeId, 'content', $block->getRawOriginal('content')),
                'submit_label' => $this->resolvedContactTranslationValue($data, $block, $localeId, 'submit_label', 'Send message'),
                'success_message' => $this->resolvedContactTranslationValue($data, $block, $localeId, 'success_message', config('contact.success_message')),
            ],
        };
    }

    private function resolvedContactTranslationValue(array $data, Block $block, int $localeId, string $field, string $default): string
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

        $existing = trim((string) ($this->existingTranslationValue($block, 'contactFormTranslations', $localeId, $field) ?? ''));

        if ($existing !== '') {
            return $existing;
        }

        $rawSettings = $this->decodeSettings($block->getRawOriginal('settings'));

        return trim((string) ($rawSettings[$field] ?? '')) ?: $default;
    }

    private function existingTranslationValue(Block $block, string $relation, int $localeId, string $field, mixed $fallback = null): mixed
    {
        $translations = $block->relationLoaded($relation)
            ? $block->getRelation($relation)
            : $block->{$relation}()->get();

        $translation = $translations->firstWhere('locale_id', $localeId);

        return $translation?->{$field} ?? $fallback;
    }

    private function submittedString(array $data, string $field): ?string
    {
        if (! array_key_exists($field, $data)) {
            return null;
        }

        $value = trim((string) $data[$field]);

        return $value !== '' ? $value : null;
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
            'text' => ['title', 'subtitle', 'content', 'meta'],
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
