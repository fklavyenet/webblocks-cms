<?php

namespace WebBlocks\Cms\Support\Pages;

use App\Models\User;
use WebBlocks\Cms\Support\Audit\CurrentActorResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use JsonException;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockButtonTranslation;
use WebBlocks\Cms\Models\BlockContactFormTranslation;
use WebBlocks\Cms\Models\BlockImageTranslation;
use WebBlocks\Cms\Models\BlockTextTranslation;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Layout;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\BlockTranslationRegistry;

class PageJsonImporter
{
    private const SCHEMA = 'webblocks.cms.page.v1';

    public function __construct(
        private readonly CurrentActorResolver $currentActorResolver,
        private readonly PageAssetPathValidator $pageAssetPathValidator,
        private readonly PageAssetSyncService $pageAssetSyncService,
        private readonly PageRevisionManager $pageRevisionManager,
        private readonly BlockTranslationRegistry $blockTranslationRegistry,
    ) {}

    public function import(int $targetSiteId, UploadedFile $file, User $actor): Page
    {
        $site = Site::query()
            ->with(['enabledLocales' => fn ($query) => $query->orderByDesc('is_default')->orderBy('name')])
            ->findOrFail($targetSiteId);

        $payload = $this->decodePayload($file);
        $normalized = $this->normalizePayload($payload, $site);
        $actorMeta = $this->currentActorResolver->resolve($actor, 'admin/page_import');

        return DB::transaction(function () use ($site, $normalized, $actor, $actorMeta): Page {
            $page = Page::query()->create([
                'site_id' => $site->id,
                'title' => $normalized['default_translation']['name'],
                'slug' => $normalized['default_translation']['slug'],
                'page_type' => Page::TYPE_DEFAULT,
                'layout_id' => $normalized['page']['layout_id'],
                'settings' => $normalized['page']['settings'],
                'status' => Page::STATUS_DRAFT,
                'published_at' => null,
                'review_requested_at' => null,
                'created_by_user_id' => $actorMeta['user_id'],
                'updated_by_user_id' => $actorMeta['user_id'],
            ]);

            $page->translations()->delete();

            foreach ($normalized['translations'] as $translation) {
                PageTranslation::query()->create([
                    'page_id' => $page->id,
                    'site_id' => $site->id,
                    'locale_id' => $translation['locale_id'],
                    'name' => $translation['name'],
                    'slug' => $translation['slug'],
                    'path' => $translation['path'],
                    'seo_title' => $translation['seo_title'],
                    'seo_description' => $translation['seo_description'],
                    'seo_keywords' => $translation['seo_keywords'],
                    'og_title' => $translation['og_title'],
                    'og_description' => $translation['og_description'],
                    'og_image_media_id' => null,
                ]);
            }

            foreach ($normalized['slots'] as $slot) {
                PageSlot::query()->create([
                    'page_id' => $page->id,
                    'slot_type_id' => $slot['slot_type_id'],
                    'source_type' => $slot['source_type'],
                    'shared_slot_id' => $slot['shared_slot_id'],
                    'sort_order' => $slot['sort_order'],
                    'settings' => $slot['settings'],
                ]);
            }

            $blockIdMap = [];

            foreach ($normalized['blocks'] as $block) {
                $created = Block::query()->create([
                    'page_id' => $page->id,
                    'parent_id' => null,
                    'type' => $block['type'],
                    'block_type_id' => $block['block_type_id'],
                    'source_type' => $block['source_type'],
                    'slot' => $block['slot_slug'],
                    'slot_type_id' => $block['slot_type_id'],
                    'sort_order' => $block['sort_order'],
                    'title' => null,
                    'subtitle' => null,
                    'content' => null,
                    'url' => $block['url'],
                    'media_id' => null,
                    'variant' => $block['variant'],
                    'meta' => $block['meta'],
                    'settings' => $block['settings'],
                    'status' => $block['status'],
                    'is_system' => $block['is_system'],
                ]);

                $blockIdMap[$block['tmp_id']] = $created->id;
                $this->writeBlockTranslations($created, $block['translations']);
            }

            foreach ($normalized['blocks'] as $block) {
                if ($block['parent_tmp_id'] === null) {
                    continue;
                }

                Block::query()->whereKey($blockIdMap[$block['tmp_id']])->update([
                    'parent_id' => $blockIdMap[$block['parent_tmp_id']] ?? null,
                ]);
            }

            if ($normalized['page_assets'] !== []) {
                $this->pageAssetSyncService->sync($page, $normalized['page_assets']);
            }

            $importedPage = $page->fresh(['site', 'translations.locale', 'slots.sharedSlot', 'slots.slotType']);

            $this->pageRevisionManager->capture(
                $importedPage,
                $actor,
                'Page imported',
                'Page was created from a single-page JSON import payload.',
                event: 'page_created',
                source: 'admin/page_import',
            );

            return $importedPage;
        });
    }

    private function decodePayload(UploadedFile $file): array
    {
        try {
            $decoded = json_decode($file->get(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages([
                'json_file' => ['The uploaded file must contain valid JSON.'],
            ]);
        }

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'json_file' => ['The uploaded JSON payload must decode to an object.'],
            ]);
        }

        return $decoded;
    }

    private function normalizePayload(array $payload, Site $site): array
    {
        $schema = trim((string) ($payload['schema'] ?? ''));

        if ($schema !== self::SCHEMA) {
            throw ValidationException::withMessages([
                'json_file' => ['Unsupported page import schema. Expected '.self::SCHEMA.'.'],
            ]);
        }

        $allowedKeys = ['schema', 'page', 'translations', 'slots', 'blocks', 'page_assets'];
        $unknownKeys = array_diff(array_keys($payload), $allowedKeys);

        if ($unknownKeys !== []) {
            throw ValidationException::withMessages([
                'json_file' => ['Unsupported top-level keys: '.implode(', ', $unknownKeys).'.'],
            ]);
        }

        $pageData = is_array($payload['page'] ?? null) ? $payload['page'] : [];
        $translationsData = $payload['translations'] ?? null;
        $slotsData = $payload['slots'] ?? [];
        $blocksData = $payload['blocks'] ?? [];
        $pageAssetsData = $payload['page_assets'] ?? [];

        if (! is_array($translationsData) || $translationsData === []) {
            throw ValidationException::withMessages([
                'json_file' => ['The payload must include at least one translation keyed by locale code.'],
            ]);
        }

        if (! is_array($slotsData)) {
            throw ValidationException::withMessages([
                'slots' => ['Slots must be an array.'],
            ]);
        }

        if (! is_array($blocksData)) {
            throw ValidationException::withMessages([
                'blocks' => ['Blocks must be an array.'],
            ]);
        }

        if (! is_array($pageAssetsData)) {
            throw ValidationException::withMessages([
                'page_assets' => ['Page assets must be an array.'],
            ]);
        }

        $enabledLocales = $site->enabledLocales->keyBy(fn (Locale $locale) => strtolower($locale->code));
        $defaultLocale = $site->enabledLocales->firstWhere('is_default', true)
            ?? $site->enabledLocales->first();

        if (! $defaultLocale) {
            throw ValidationException::withMessages([
                'site_id' => ['The selected site does not have any enabled locales.'],
            ]);
        }

        $normalizedTranslations = collect($translationsData)
            ->map(function ($translation, $localeCode) use ($enabledLocales) {
                $localeCode = strtolower(trim((string) $localeCode));
                $locale = $enabledLocales->get($localeCode);

                if (! $locale) {
                    throw ValidationException::withMessages([
                        'translations' => ['Locale ['.$localeCode.'] is not enabled for the selected site.'],
                    ]);
                }

                if (! is_array($translation)) {
                    throw ValidationException::withMessages([
                        'translations' => ['Translation ['.$localeCode.'] must be an object.'],
                    ]);
                }

                $unknownKeys = array_diff(array_keys($translation), ['name', 'slug', 'seo_title', 'seo_description', 'seo_keywords', 'og_title', 'og_description']);

                if ($unknownKeys !== []) {
                    throw ValidationException::withMessages([
                        'translations' => ['Translation ['.$localeCode.'] contains unsupported keys: '.implode(', ', $unknownKeys).'.'],
                    ]);
                }

                $name = trim((string) ($translation['name'] ?? ''));
                $slug = Str::slug(trim((string) ($translation['slug'] ?? '')));

                if ($name === '' || $slug === '') {
                    throw ValidationException::withMessages([
                        'translations' => ['Translation ['.$localeCode.'] requires both name and slug.'],
                    ]);
                }

                return [
                    'locale_id' => $locale->id,
                    'locale_code' => $localeCode,
                    'name' => $name,
                    'slug' => $slug,
                    'path' => PageTranslation::pathFromSlug($slug),
                    'seo_title' => $this->nullableString($translation['seo_title'] ?? null),
                    'seo_description' => $this->nullableString($translation['seo_description'] ?? null),
                    'seo_keywords' => $this->nullableString($translation['seo_keywords'] ?? null),
                    'og_title' => $this->nullableString($translation['og_title'] ?? null),
                    'og_description' => $this->nullableString($translation['og_description'] ?? null),
                ];
            })
            ->values();

        $defaultTranslation = $normalizedTranslations->firstWhere('locale_id', $defaultLocale->id)
            ?? $normalizedTranslations->first();

        $this->guardPathConflicts($site, $normalizedTranslations);

        $layoutId = null;
        $layoutSlug = trim((string) ($pageData['layout_slug'] ?? ''));

        if ($layoutSlug !== '') {
            $layoutId = Layout::query()->where('slug', $layoutSlug)->value('id');

            if (! $layoutId) {
                throw ValidationException::withMessages([
                    'page.layout_slug' => ['Unknown layout_slug ['.$layoutSlug.'].'],
                ]);
            }
        }

        $settings = Page::sanitizeSettings(
            Arr::only(is_array($pageData['settings'] ?? null) ? $pageData['settings'] : [], ['public_shell']),
            $pageData['public_shell'] ?? null,
        );

        $normalizedSlots = $this->normalizeSlots($slotsData, $site, $settings['public_shell'] ?? 'default');
        $normalizedBlocks = $this->normalizeBlocks($blocksData, $normalizedTranslations, $normalizedSlots);
        $normalizedPageAssets = $this->normalizePageAssets($pageAssetsData);

        return [
            'page' => [
                'layout_id' => $layoutId,
                'settings' => $settings,
            ],
            'default_translation' => $defaultTranslation,
            'translations' => $normalizedTranslations,
            'slots' => $normalizedSlots,
            'blocks' => $normalizedBlocks,
            'page_assets' => $normalizedPageAssets,
        ];
    }

    private function normalizeSlots(array $slotsData, Site $site, string $publicShell): array
    {
        return collect($slotsData)
            ->map(function ($slot, int $index) use ($site, $publicShell) {
                if (! is_array($slot)) {
                    throw ValidationException::withMessages([
                        'slots' => ['Each slot must be an object.'],
                    ]);
                }

                $unknownKeys = array_diff(array_keys($slot), ['slot', 'source_type', 'shared_slot_handle', 'enabled', 'sort_order', 'settings']);

                if ($unknownKeys !== []) {
                    throw ValidationException::withMessages([
                        'slots' => ['Slot ['.$index.'] contains unsupported keys: '.implode(', ', $unknownKeys).'.'],
                    ]);
                }

                $slotSlug = Str::slug(trim((string) ($slot['slot'] ?? '')));
                $slotType = SlotType::query()->where('slug', $slotSlug)->first();

                if (! $slotType) {
                    throw ValidationException::withMessages([
                        'slots' => ['Slot ['.$index.'] references unknown slot ['.$slotSlug.'].'],
                    ]);
                }

                $sourceType = PageSlot::normalizeSourceType($slot['source_type'] ?? PageSlot::SOURCE_TYPE_PAGE);
                $sharedSlotId = null;

                if ($sourceType === PageSlot::SOURCE_TYPE_SHARED_SLOT) {
                    $sharedSlotHandle = Str::slug(trim((string) ($slot['shared_slot_handle'] ?? '')));

                    if ($sharedSlotHandle === '') {
                        throw ValidationException::withMessages([
                            'slots' => ['Slot ['.$slotSlug.'] requires shared_slot_handle when source_type is shared_slot.'],
                        ]);
                    }

                    $sharedSlot = SharedSlot::query()
                        ->where('site_id', $site->id)
                        ->where('handle', $sharedSlotHandle)
                        ->first();

                    if (! $sharedSlot) {
                        throw ValidationException::withMessages([
                            'slots' => ['Shared Slot handle ['.$sharedSlotHandle.'] was not found on the selected site for slot ['.$slotSlug.'].'],
                        ]);
                    }

                    $pageShell = Page::normalizePublicShellHandle($publicShell);
                    $page = new Page(['site_id' => $site->id, 'settings' => ['public_shell' => $pageShell]]);

                    if (! $sharedSlot->isCompatibleWithPageSlot($page, $slotSlug)) {
                        throw ValidationException::withMessages([
                            'slots' => ['Shared Slot ['.$sharedSlotHandle.'] is not compatible with slot ['.$slotSlug.'] on the selected site.'],
                        ]);
                    }

                    $sharedSlotId = $sharedSlot->id;
                }

                return [
                    'slot_type_id' => $slotType->id,
                    'slot_slug' => $slotSlug,
                    'source_type' => $sourceType,
                    'shared_slot_id' => $sharedSlotId,
                    'sort_order' => isset($slot['sort_order']) ? max((int) $slot['sort_order'], 0) : $index,
                    'settings' => PageSlot::sanitizeSettings($slot['settings'] ?? null),
                ];
            })
            ->sortBy('sort_order')
            ->values()
            ->all();
    }

    private function normalizeBlocks(array $blocksData, Collection $translations, array $slots): array
    {
        $slotMap = collect($slots)->keyBy('slot_slug');
        $localeMap = $translations->keyBy('locale_code');

        return collect($blocksData)
            ->map(function ($block, int $index) use ($slotMap, $localeMap) {
                if (! is_array($block)) {
                    throw ValidationException::withMessages([
                        'blocks' => ['Each block must be an object.'],
                    ]);
                }

                $unknownKeys = array_diff(array_keys($block), ['id', 'parent_id', 'slot', 'type', 'status', 'sort_order', 'url', 'variant', 'meta', 'settings', 'translations']);

                if ($unknownKeys !== []) {
                    throw ValidationException::withMessages([
                        'blocks' => ['Block ['.$index.'] contains unsupported keys: '.implode(', ', $unknownKeys).'.'],
                    ]);
                }

                $tmpId = trim((string) ($block['id'] ?? ''));
                $parentTmpId = trim((string) ($block['parent_id'] ?? ''));
                $slotSlug = Str::slug(trim((string) ($block['slot'] ?? '')));
                $typeSlug = trim((string) ($block['type'] ?? ''));
                $status = trim((string) ($block['status'] ?? 'published'));

                if ($tmpId === '' || $slotSlug === '' || $typeSlug === '') {
                    throw ValidationException::withMessages([
                        'blocks' => ['Each block requires id, slot, and type.'],
                    ]);
                }

                if (! $slotMap->has($slotSlug)) {
                    throw ValidationException::withMessages([
                        'blocks' => ['Block ['.$tmpId.'] references unknown slot ['.$slotSlug.'].'],
                    ]);
                }

                if (! in_array($status, ['draft', 'published'], true)) {
                    throw ValidationException::withMessages([
                        'blocks' => ['Block ['.$tmpId.'] status must be draft or published.'],
                    ]);
                }

                $blockType = BlockType::query()->where('slug', $typeSlug)->first();

                if (! $blockType) {
                    throw ValidationException::withMessages([
                        'blocks' => ['Block ['.$tmpId.'] references unknown block type ['.$typeSlug.'].'],
                    ]);
                }

                $translationPayload = $this->normalizeBlockTranslations(
                    tmpId: $tmpId,
                    typeSlug: $typeSlug,
                    translations: $block['translations'] ?? [],
                    localeMap: $localeMap,
                );

                return [
                    'tmp_id' => $tmpId,
                    'parent_tmp_id' => $parentTmpId !== '' ? $parentTmpId : null,
                    'slot_slug' => $slotSlug,
                    'slot_type_id' => $slotMap[$slotSlug]['slot_type_id'],
                    'type' => $typeSlug,
                    'block_type_id' => $blockType->id,
                    'source_type' => $blockType->source_type ?: 'static',
                    'sort_order' => isset($block['sort_order']) ? max((int) $block['sort_order'], 0) : $index,
                    'status' => $status,
                    'url' => $this->nullableString($block['url'] ?? null),
                    'variant' => $this->nullableString($block['variant'] ?? null),
                    'meta' => $this->nullableString($block['meta'] ?? null),
                    'settings' => $this->normalizeJsonishSettings($block['settings'] ?? null),
                    'is_system' => (bool) $blockType->is_system,
                    'translations' => $translationPayload,
                ];
            })
            ->tap(function (Collection $blocks): void {
                $tmpIds = $blocks->pluck('tmp_id');

                if ($tmpIds->unique()->count() !== $tmpIds->count()) {
                    throw ValidationException::withMessages([
                        'blocks' => ['Block ids must be unique within the payload.'],
                    ]);
                }

                foreach ($blocks as $block) {
                    if ($block['parent_tmp_id'] !== null && ! $tmpIds->contains($block['parent_tmp_id'])) {
                        throw ValidationException::withMessages([
                            'blocks' => ['Block ['.$block['tmp_id'].'] references missing parent ['.$block['parent_tmp_id'].'].'],
                        ]);
                    }
                }
            })
            ->sortBy(fn (array $block) => sprintf('%010d-%s', $block['sort_order'], $block['tmp_id']))
            ->values()
            ->all();
    }

    private function normalizeBlockTranslations(string $tmpId, string $typeSlug, mixed $translations, Collection $localeMap): array
    {
        if (! is_array($translations)) {
            throw ValidationException::withMessages([
                'blocks' => ['Block ['.$tmpId.'] translations must be an object keyed by locale code.'],
            ]);
        }

        $family = $this->blockTranslationRegistry->familyFor($typeSlug);

        if ($family === null) {
            if ($translations !== []) {
                throw ValidationException::withMessages([
                    'blocks' => ['Block ['.$tmpId.'] type ['.$typeSlug.'] does not support imported translations.'],
                ]);
            }

            return [];
        }

        $allowedFields = $this->blockTranslationRegistry->translatedFieldMap($family);

        return collect($translations)
            ->map(function ($translation, $localeCode) use ($tmpId, $localeMap, $allowedFields) {
                $localeCode = strtolower(trim((string) $localeCode));
                $locale = $localeMap->get($localeCode);

                if (! $locale) {
                    throw ValidationException::withMessages([
                        'blocks' => ['Block ['.$tmpId.'] translation locale ['.$localeCode.'] is not enabled for the selected site.'],
                    ]);
                }

                if (! is_array($translation)) {
                    throw ValidationException::withMessages([
                        'blocks' => ['Block ['.$tmpId.'] translation ['.$localeCode.'] must be an object.'],
                    ]);
                }

                $unknownKeys = array_diff(array_keys($translation), $allowedFields);

                if ($unknownKeys !== []) {
                    throw ValidationException::withMessages([
                        'blocks' => ['Block ['.$tmpId.'] translation ['.$localeCode.'] contains unsupported keys: '.implode(', ', $unknownKeys).'.'],
                    ]);
                }

                return [
                    'locale_id' => $locale['locale_id'],
                    'locale_code' => $localeCode,
                    'fields' => collect($translation)
                        ->mapWithKeys(fn ($value, $field) => [$field => $this->nullableString($value)])
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    private function normalizePageAssets(array $pageAssetsData): array
    {
        return collect($pageAssetsData)
            ->map(function ($asset, int $index) {
                if (! is_array($asset)) {
                    throw ValidationException::withMessages([
                        'page_assets' => ['Each page asset must be an object.'],
                    ]);
                }

                $unknownKeys = array_diff(array_keys($asset), ['type', 'path', 'sort_order', 'is_enabled', 'is_defer', 'is_async', 'is_module']);

                if ($unknownKeys !== []) {
                    throw ValidationException::withMessages([
                        'page_assets' => ['Page asset ['.$index.'] contains unsupported keys: '.implode(', ', $unknownKeys).'.'],
                    ]);
                }

                $type = $this->pageAssetPathValidator->normalizeType($asset['type'] ?? null);

                try {
                    $path = $this->pageAssetPathValidator->normalizeForStorage($type, $asset['path'] ?? '');
                } catch (InvalidArgumentException $exception) {
                    throw ValidationException::withMessages([
                        'json_file' => ['Page asset ['.$index.'] is invalid: '.$exception->getMessage()],
                    ]);
                }

                return [
                    'type' => $type,
                    'path' => $path,
                    'sort_order' => isset($asset['sort_order']) ? max((int) $asset['sort_order'], 0) : $index,
                    'is_enabled' => (bool) ($asset['is_enabled'] ?? true),
                    'is_defer' => $type === 'js' ? (bool) ($asset['is_defer'] ?? true) : false,
                    'is_async' => $type === 'js' ? (bool) ($asset['is_async'] ?? false) : false,
                    'is_module' => $type === 'js' ? (bool) ($asset['is_module'] ?? false) : false,
                ];
            })
            ->sortBy('sort_order')
            ->values()
            ->all();
    }

    private function writeBlockTranslations(Block $block, array $translations): void
    {
        $family = $this->blockTranslationRegistry->familyFor($block);

        foreach ($translations as $translation) {
            $payload = ['block_id' => $block->id, 'locale_id' => $translation['locale_id']] + $translation['fields'];

            match ($family) {
                'text' => BlockTextTranslation::query()->create($payload),
                'button' => BlockButtonTranslation::query()->create($payload),
                'image' => BlockImageTranslation::query()->create($payload),
                'contact_form' => BlockContactFormTranslation::query()->create($payload),
                default => null,
            };
        }
    }

    private function guardPathConflicts(Site $site, Collection $translations): void
    {
        foreach ($translations as $translation) {
            $exists = PageTranslation::query()
                ->where('site_id', $site->id)
                ->where('locale_id', $translation['locale_id'])
                ->where('path', $translation['path'])
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'translations' => ['Path conflict for locale ['.$translation['locale_code'].'] at ['.$translation['path'].'] on the selected site.'],
                ]);
            }
        }
    }

    private function normalizeJsonishSettings(mixed $settings): ?string
    {
        if ($settings === null || $settings === '') {
            return null;
        }

        if (is_array($settings)) {
            return $settings === [] ? null : json_encode($settings, JSON_UNESCAPED_SLASHES);
        }

        if (is_string($settings)) {
            $trimmed = trim($settings);

            if ($trimmed === '') {
                return null;
            }

            json_decode($trimmed, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw ValidationException::withMessages([
                    'blocks' => ['Block settings must be valid JSON when provided as a string.'],
                ]);
            }

            return $trimmed;
        }

        throw ValidationException::withMessages([
            'blocks' => ['Block settings must be an object or JSON string.'],
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }
}
