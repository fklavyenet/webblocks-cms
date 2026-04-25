<?php

namespace App\Support\Pages;

use App\Models\Block;
use App\Models\Locale;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use App\Support\Blocks\BlockTranslationWriter;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PageRevisionManager
{
    private const SNAPSHOT_SCHEMA_VERSION = 1;

    public function __construct(
        private readonly PageWorkflowManager $workflowManager,
        private readonly BlockTranslationWriter $blockTranslationWriter,
    ) {}

    public function canView(User $user, Page $page): bool
    {
        return $user->canAccessAdmin()
            && $user->hasSiteAccess($page->site_id)
            && ($user->isSuperAdmin() || $user->isSiteAdmin() || $user->isEditor());
    }

    public function canRestore(User $user, Page $page): bool
    {
        return ($user->isSuperAdmin() || $user->isSiteAdmin())
            && $user->hasSiteAccess($page->site_id);
    }

    public function revisionsTableExists(): bool
    {
        try {
            return Schema::hasTable('page_revisions');
        } catch (Throwable) {
            return false;
        }
    }

    public function capture(Page $page, ?User $actor = null, ?string $label = null, ?string $reason = null, ?PageRevision $restoredFrom = null): PageRevision
    {
        if (! $this->revisionsTableExists()) {
            throw new \RuntimeException('Page revisions are not ready. Run the latest migrations before using revisions.');
        }

        $page->loadMissing([
            'site',
            'translations.locale',
            'slots.slotType',
            'blocks' => fn ($query) => $query
                ->with([
                    'blockAssets',
                    'textTranslations',
                    'buttonTranslations',
                    'imageTranslations',
                    'contactFormTranslations',
                ])
                ->orderBy('sort_order')
                ->orderBy('id'),
        ]);

        return PageRevision::create([
            'page_id' => $page->id,
            'site_id' => $page->site_id,
            'created_by' => $actor?->id,
            'label' => $label,
            'reason' => $reason,
            'snapshot' => $this->snapshot($page),
            'restored_from_page_revision_id' => $restoredFrom?->id,
        ]);
    }

    public function restore(Page $page, PageRevision $revision, User $actor): void
    {
        if (! $this->revisionsTableExists()) {
            throw new \RuntimeException('Page revisions are not ready. Run the latest migrations before using revisions.');
        }

        abort_unless($page->id === $revision->page_id, 404);
        abort_unless((int) $page->site_id === (int) $revision->site_id, 404);

        DB::transaction(function () use ($page, $revision, $actor): void {
            $this->capture(
                $page->fresh(),
                $actor,
                'Pre-restore safety snapshot',
                'Current page state was saved before restore.',
            );

            $this->applySnapshot($page->fresh(), $revision->snapshot ?? []);

            $this->capture(
                $page->fresh(),
                $actor,
                'Revision restored',
                'Page content was restored from revision #'.$revision->id.'.',
                $revision,
            );
        });
    }

    private function snapshot(Page $page): array
    {
        $defaultTranslation = $page->defaultTranslation();
        $blocks = $page->blocks
            ->sortBy(fn (Block $block) => sprintf('%04d-%04d', $block->parent_id ?? 0, $block->sort_order))
            ->values();

        return [
            'schema_version' => self::SNAPSHOT_SCHEMA_VERSION,
            'captured_at' => now()->toIso8601String(),
            'page' => [
                'title' => $defaultTranslation?->name ?? $page->name,
                'slug' => $defaultTranslation?->slug ?? $page->slug,
                'page_type' => $page->page_type,
                'page_type_id' => $page->page_type_id,
                'layout_id' => $page->layout_id,
                'status' => $page->status,
                'published_at' => $page->published_at?->toIso8601String(),
                'review_requested_at' => $page->review_requested_at?->toIso8601String(),
            ],
            'translations' => $page->translations
                ->sortBy('locale_id')
                ->values()
                ->map(fn ($translation) => [
                    'locale_id' => $translation->locale_id,
                    'name' => $translation->name,
                    'slug' => $translation->slug,
                    'path' => $translation->path,
                ])
                ->all(),
            'slots' => $page->slots
                ->sortBy('sort_order')
                ->values()
                ->map(fn ($slot) => [
                    'slot_type_id' => $slot->slot_type_id,
                    'sort_order' => $slot->sort_order,
                ])
                ->all(),
            'blocks' => $blocks
                ->map(fn (Block $block) => [
                    'snapshot_id' => $block->id,
                    'parent_snapshot_id' => $block->parent_id,
                    'type' => $block->type,
                    'block_type_id' => $block->block_type_id,
                    'source_type' => $block->source_type,
                    'slot' => $block->slot,
                    'slot_type_id' => $block->slot_type_id,
                    'sort_order' => $block->sort_order,
                    'title' => $block->getRawOriginal('title'),
                    'subtitle' => $block->getRawOriginal('subtitle'),
                    'content' => $block->getRawOriginal('content'),
                    'url' => $block->url,
                    'asset_id' => $block->asset_id,
                    'variant' => $block->variant,
                    'meta' => $block->meta,
                    'settings' => $block->getRawOriginal('settings'),
                    'status' => $block->status,
                    'is_system' => $block->is_system,
                    'block_assets' => $block->blockAssets
                        ->sortBy('position')
                        ->values()
                        ->map(fn ($asset) => [
                            'asset_id' => $asset->asset_id,
                            'role' => $asset->role,
                            'position' => $asset->position,
                        ])
                        ->all(),
                    'text_translations' => $block->textTranslations
                        ->sortBy('locale_id')
                        ->values()
                        ->map(fn ($translation) => [
                            'locale_id' => $translation->locale_id,
                            'title' => $translation->title,
                            'subtitle' => $translation->subtitle,
                            'content' => $translation->content,
                        ])
                        ->all(),
                    'button_translations' => $block->buttonTranslations
                        ->sortBy('locale_id')
                        ->values()
                        ->map(fn ($translation) => [
                            'locale_id' => $translation->locale_id,
                            'title' => $translation->title,
                        ])
                        ->all(),
                    'image_translations' => $block->imageTranslations
                        ->sortBy('locale_id')
                        ->values()
                        ->map(fn ($translation) => [
                            'locale_id' => $translation->locale_id,
                            'caption' => $translation->caption,
                            'alt_text' => $translation->alt_text,
                        ])
                        ->all(),
                    'contact_form_translations' => $block->contactFormTranslations
                        ->sortBy('locale_id')
                        ->values()
                        ->map(fn ($translation) => [
                            'locale_id' => $translation->locale_id,
                            'title' => $translation->title,
                            'content' => $translation->content,
                            'submit_label' => $translation->submit_label,
                            'success_message' => $translation->success_message,
                        ])
                        ->all(),
                ])
                ->all(),
        ];
    }

    private function applySnapshot(Page $page, array $snapshot): void
    {
        $pageData = Arr::get($snapshot, 'page', []);

        $page->forceFill([
            'page_type' => Arr::get($pageData, 'page_type', $page->page_type),
            'page_type_id' => Arr::get($pageData, 'page_type_id', $page->page_type_id),
            'layout_id' => Arr::get($pageData, 'layout_id', $page->layout_id),
            'status' => Arr::get($pageData, 'status', $page->status),
            'published_at' => $this->dateOrNull(Arr::get($pageData, 'published_at')),
            'review_requested_at' => $this->dateOrNull(Arr::get($pageData, 'review_requested_at')),
        ])->save();

        $page->translations()->delete();

        foreach (Arr::get($snapshot, 'translations', []) as $translation) {
            $page->translations()->create([
                'locale_id' => $translation['locale_id'],
                'name' => $translation['name'],
                'slug' => $translation['slug'],
                'path' => $translation['path'] ?? null,
            ]);
        }

        $page->slots()->delete();

        foreach (Arr::get($snapshot, 'slots', []) as $slot) {
            $page->slots()->create([
                'slot_type_id' => $slot['slot_type_id'],
                'sort_order' => $slot['sort_order'],
            ]);
        }

        $page->blocks()->delete();

        $snapshotBlocks = collect(Arr::get($snapshot, 'blocks', []));
        $idMap = [];
        $remaining = $snapshotBlocks->values();

        while ($remaining->isNotEmpty()) {
            $createdThisPass = 0;

            $nextRemaining = $remaining->reject(function (array $block) use ($page, &$idMap, &$createdThisPass) {
                $parentSnapshotId = $block['parent_snapshot_id'] ?? null;

                if ($parentSnapshotId !== null && ! array_key_exists($parentSnapshotId, $idMap)) {
                    return false;
                }

                $created = $page->blocks()->create([
                    'parent_id' => $parentSnapshotId === null ? null : $idMap[$parentSnapshotId],
                    'type' => $block['type'],
                    'block_type_id' => $block['block_type_id'],
                    'source_type' => $block['source_type'],
                    'slot' => $block['slot'],
                    'slot_type_id' => $block['slot_type_id'],
                    'sort_order' => $block['sort_order'],
                    'title' => $block['title'],
                    'subtitle' => $block['subtitle'],
                    'content' => $block['content'],
                    'url' => $block['url'],
                    'asset_id' => $block['asset_id'],
                    'variant' => $block['variant'],
                    'meta' => $block['meta'],
                    'settings' => $block['settings'],
                    'status' => $block['status'],
                    'is_system' => $block['is_system'],
                ]);

                $idMap[$block['snapshot_id']] = $created->id;
                $createdThisPass++;

                return true;
            })->values();

            if ($createdThisPass === 0) {
                throw new \RuntimeException('Page revision restore could not resolve block parent relationships.');
            }

            $remaining = $nextRemaining;
        }

        foreach ($snapshotBlocks as $block) {
            $restoredBlock = Block::query()->findOrFail($idMap[$block['snapshot_id']]);

            foreach ($block['block_assets'] ?? [] as $asset) {
                $restoredBlock->blockAssets()->create([
                    'asset_id' => $asset['asset_id'],
                    'role' => $asset['role'],
                    'position' => $asset['position'],
                ]);
            }

            foreach ($block['text_translations'] ?? [] as $translation) {
                $restoredBlock->textTranslations()->create($translation);
            }

            foreach ($block['button_translations'] ?? [] as $translation) {
                $restoredBlock->buttonTranslations()->create($translation);
            }

            foreach ($block['image_translations'] ?? [] as $translation) {
                $restoredBlock->imageTranslations()->create($translation);
            }

            foreach ($block['contact_form_translations'] ?? [] as $translation) {
                $restoredBlock->contactFormTranslations()->create($translation);
            }

            $this->normalizeRestoredBlock($restoredBlock, $block);
        }
    }

    private function normalizeRestoredBlock(Block $block, array $snapshotBlock): void
    {
        $legacyFallbackUsed = $this->ensureLegacySnapshotFallbackTranslations($block, $snapshotBlock);

        if ($legacyFallbackUsed || $this->blockHasTranslationRows($block)) {
            // Older revisions may only carry canonical block copy. Restore those payloads into
            // translation rows and immediately clear canonical fields so reconstructed content
            // follows the same authoritative translation model as current revisions.
            $this->blockTranslationWriter->normalizeCanonicalStorage($block->fresh([
                'textTranslations',
                'buttonTranslations',
                'imageTranslations',
                'contactFormTranslations',
            ]));
        }
    }

    private function ensureLegacySnapshotFallbackTranslations(Block $block, array $snapshotBlock): bool
    {
        if (
            ($snapshotBlock['text_translations'] ?? []) !== []
            || ($snapshotBlock['button_translations'] ?? []) !== []
            || ($snapshotBlock['image_translations'] ?? []) !== []
            || ($snapshotBlock['contact_form_translations'] ?? []) !== []
        ) {
            return false;
        }

        $defaultLocaleId = Locale::query()->where('is_default', true)->value('id');

        if (! $defaultLocaleId) {
            return false;
        }

        $rawTitle = $snapshotBlock['title'] ?? null;
        $rawSubtitle = $snapshotBlock['subtitle'] ?? null;
        $rawContent = $snapshotBlock['content'] ?? null;
        $rawSettings = $snapshotBlock['settings'] ?? null;

        return match ($block->type) {
            'column_item', 'section', 'text', 'rich-text', 'heading', 'callout', 'quote', 'faq' => $this->seedLegacyTextSnapshotFallback($block, $defaultLocaleId, $rawTitle, $rawSubtitle, $rawContent),
            'button' => $this->seedLegacyButtonSnapshotFallback($block, $defaultLocaleId, $rawTitle),
            'image' => $this->seedLegacyImageSnapshotFallback($block, $defaultLocaleId, $rawTitle, $rawSubtitle),
            'contact_form' => $this->seedLegacyContactFormSnapshotFallback($block, $defaultLocaleId, $rawTitle, $rawContent, $rawSettings),
            default => false,
        };
    }

    private function seedLegacyTextSnapshotFallback(Block $block, int $defaultLocaleId, mixed $title, mixed $subtitle, mixed $content): bool
    {
        if (! is_string($title) && ! is_string($subtitle) && ! is_string($content)) {
            return false;
        }

        $block->textTranslations()->updateOrCreate(
            ['locale_id' => $defaultLocaleId],
            [
                'title' => $title,
                'subtitle' => $subtitle,
                'content' => $content,
            ],
        );

        return true;
    }

    private function seedLegacyButtonSnapshotFallback(Block $block, int $defaultLocaleId, mixed $title): bool
    {
        if (! is_string($title) || trim($title) === '') {
            return false;
        }

        $block->buttonTranslations()->updateOrCreate(
            ['locale_id' => $defaultLocaleId],
            ['title' => $title],
        );

        return true;
    }

    private function seedLegacyImageSnapshotFallback(Block $block, int $defaultLocaleId, mixed $title, mixed $subtitle): bool
    {
        if (! is_string($title) && ! is_string($subtitle)) {
            return false;
        }

        $block->imageTranslations()->updateOrCreate(
            ['locale_id' => $defaultLocaleId],
            [
                'caption' => $title,
                'alt_text' => $subtitle,
            ],
        );

        return true;
    }

    private function seedLegacyContactFormSnapshotFallback(Block $block, int $defaultLocaleId, mixed $title, mixed $content, mixed $settings): bool
    {
        $decodedSettings = is_array($settings)
            ? $settings
            : (is_string($settings) && trim($settings) !== '' ? (json_decode($settings, true) ?: []) : []);

        if (! is_string($title) && ! is_string($content) && ! isset($decodedSettings['submit_label'], $decodedSettings['success_message'])) {
            return false;
        }

        $block->contactFormTranslations()->updateOrCreate(
            ['locale_id' => $defaultLocaleId],
            [
                'title' => $title,
                'content' => $content,
                'submit_label' => trim((string) ($decodedSettings['submit_label'] ?? '')) ?: 'Send message',
                'success_message' => trim((string) ($decodedSettings['success_message'] ?? '')) ?: config('contact.success_message'),
            ],
        );

        return true;
    }

    private function blockHasTranslationRows(Block $block): bool
    {
        return $block->textTranslations()->exists()
            || $block->buttonTranslations()->exists()
            || $block->imageTranslations()->exists()
            || $block->contactFormTranslations()->exists();
    }

    private function dateOrNull(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return Carbon::parse($value);
    }
}
