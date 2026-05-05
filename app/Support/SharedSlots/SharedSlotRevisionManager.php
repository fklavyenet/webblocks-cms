<?php

namespace App\Support\SharedSlots;

use App\Models\Block;
use App\Models\Locale;
use App\Models\SharedSlot;
use App\Models\SharedSlotRevision;
use App\Models\User;
use App\Support\Blocks\BlockTranslationWriter;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SharedSlotRevisionManager
{
    private const SNAPSHOT_SCHEMA_VERSION = 1;

    public function __construct(
        private readonly SharedSlotSourcePageManager $sourcePages,
        private readonly BlockTranslationWriter $blockTranslationWriter,
    ) {}

    public function canView(User $user, SharedSlot $sharedSlot): bool
    {
        return $user->canAccessAdmin()
            && $user->hasSiteAccess($sharedSlot->site_id)
            && ($user->isSuperAdmin() || $user->isSiteAdmin() || $user->isEditor());
    }

    public function canRestore(User $user, SharedSlot $sharedSlot): bool
    {
        return ($user->isSuperAdmin() || $user->isSiteAdmin())
            && $user->hasSiteAccess($sharedSlot->site_id);
    }

    public function revisionsTableExists(): bool
    {
        try {
            return Schema::hasTable('shared_slot_revisions');
        } catch (Throwable) {
            return false;
        }
    }

    public function capture(
        SharedSlot $sharedSlot,
        ?User $actor = null,
        string $sourceEvent = 'metadata_updated',
        ?string $label = null,
        ?string $summary = null,
        ?SharedSlotRevision $restoredFrom = null,
        bool $force = false,
    ): SharedSlotRevision {
        if (! $this->revisionsTableExists()) {
            throw new \RuntimeException('Shared Slot revisions are not ready. Run the latest migrations before using revisions.');
        }

        $sharedSlot = $sharedSlot->fresh(['site']) ?? $sharedSlot->loadMissing('site');
        $snapshot = $this->snapshot($sharedSlot);

        if (! $force) {
            $latest = SharedSlotRevision::query()
                ->where('shared_slot_id', $sharedSlot->id)
                ->latest('id')
                ->first();

            if ($latest && ($latest->snapshot ?? []) == $snapshot) {
                return $latest;
            }
        }

        return SharedSlotRevision::query()->create([
            'shared_slot_id' => $sharedSlot->id,
            'site_id' => $sharedSlot->site_id,
            'user_id' => $actor?->id,
            'source_event' => $sourceEvent,
            'label' => $label,
            'summary' => $summary,
            'snapshot' => $snapshot,
            'restored_from_shared_slot_revision_id' => $restoredFrom?->id,
        ]);
    }

    public function restore(SharedSlot $sharedSlot, SharedSlotRevision $revision, User $actor): void
    {
        if (! $this->revisionsTableExists()) {
            throw new \RuntimeException('Shared Slot revisions are not ready. Run the latest migrations before using revisions.');
        }

        abort_unless($sharedSlot->id === $revision->shared_slot_id, 404);
        abort_unless((int) $sharedSlot->site_id === (int) $revision->site_id, 404);

        DB::transaction(function () use ($sharedSlot, $revision, $actor): void {
            $lockedSharedSlot = SharedSlot::query()->lockForUpdate()->findOrFail($sharedSlot->id);
            $lockedRevision = SharedSlotRevision::query()->findOrFail($revision->id);

            $this->capture(
                $lockedSharedSlot,
                $actor,
                'pre_restore_safety_snapshot',
                'Pre-restore safety snapshot',
                'Current Shared Slot state was saved before restore.',
                force: true,
            );

            $this->applySnapshot($lockedSharedSlot, $lockedRevision->snapshot ?? []);

            $this->capture(
                $lockedSharedSlot->fresh(),
                $actor,
                'restored',
                'Revision restored',
                'Shared Slot content was restored from revision #'.$lockedRevision->id.'.',
                $lockedRevision,
                true,
            );
        });
    }

    private function snapshot(SharedSlot $sharedSlot): array
    {
        $sourcePage = $this->sourcePages->ensureFor($sharedSlot);
        $sourcePage->loadMissing([
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

        $blocks = $sourcePage->blocks
            ->sortBy(fn (Block $block) => sprintf('%04d-%04d', $block->parent_id ?? 0, $block->sort_order))
            ->values();

        return [
            'schema_version' => self::SNAPSHOT_SCHEMA_VERSION,
            'captured_at' => now()->toIso8601String(),
            'shared_slot' => [
                'name' => $sharedSlot->name,
                'handle' => $sharedSlot->handle,
                'slot_name' => $sharedSlot->slot_name,
                'public_shell' => $sharedSlot->public_shell,
                'is_active' => (bool) $sharedSlot->is_active,
            ],
            'source_page' => [
                'slug' => $sourcePage->slug,
                'page_type' => $sourcePage->page_type,
                'settings' => $sourcePage->getRawOriginal('settings'),
                'slot_type_id' => $sourcePage->slots->first()?->slot_type_id,
            ],
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
                            'eyebrow' => $translation->eyebrow,
                            'subtitle' => $translation->subtitle,
                            'content' => $translation->content,
                            'meta' => $translation->meta,
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

    private function applySnapshot(SharedSlot $sharedSlot, array $snapshot): void
    {
        $sharedSlotData = Arr::get($snapshot, 'shared_slot', []);

        $sharedSlot->forceFill([
            'name' => Arr::get($sharedSlotData, 'name', $sharedSlot->name),
            'handle' => Arr::get($sharedSlotData, 'handle', $sharedSlot->handle),
            'slot_name' => Arr::get($sharedSlotData, 'slot_name', $sharedSlot->slot_name),
            'public_shell' => Arr::get($sharedSlotData, 'public_shell', $sharedSlot->public_shell),
            'is_active' => (bool) Arr::get($sharedSlotData, 'is_active', $sharedSlot->is_active),
        ])->save();

        $sharedSlot = $sharedSlot->fresh();
        $sourcePage = $this->sourcePages->ensureFor($sharedSlot);
        $sourcePageData = Arr::get($snapshot, 'source_page', []);

        if (is_string(Arr::get($sourcePageData, 'slug')) && trim((string) Arr::get($sourcePageData, 'slug')) !== '') {
            $sourcePage->forceFill([
                'slug' => Arr::get($sourcePageData, 'slug'),
            ])->save();
        }

        $sourcePage->blocks()->delete();

        $snapshotBlocks = collect(Arr::get($snapshot, 'blocks', []));
        $idMap = [];
        $remaining = $snapshotBlocks->values();

        while ($remaining->isNotEmpty()) {
            $createdThisPass = 0;

            $nextRemaining = $remaining->reject(function (array $block) use ($sourcePage, &$idMap, &$createdThisPass) {
                $parentSnapshotId = $block['parent_snapshot_id'] ?? null;

                if ($parentSnapshotId !== null && ! array_key_exists($parentSnapshotId, $idMap)) {
                    return false;
                }

                $created = $sourcePage->blocks()->create([
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
                throw new \RuntimeException('Shared Slot revision restore could not resolve block parent relationships.');
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

        $this->sourcePages->ensureFor($sharedSlot->fresh());
        $this->sourcePages->rebuildAssignments($sharedSlot->fresh());
    }

    private function normalizeRestoredBlock(Block $block, array $snapshotBlock): void
    {
        $legacyFallbackUsed = $this->ensureLegacySnapshotFallbackTranslations($block, $snapshotBlock);

        if ($legacyFallbackUsed || $this->blockHasTranslationRows($block)) {
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
            'header', 'plain_text', 'content_header', 'button_link', 'card', 'column_item', 'feature-grid', 'feature-item', 'link-list', 'link-list-item', 'section', 'cta', 'text', 'rich-text', 'heading', 'callout', 'quote', 'faq' => $this->seedLegacyTextSnapshotFallback($block, $defaultLocaleId, $rawTitle, $rawSubtitle, $rawContent),
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
