<?php

namespace App\Support\Pages;

use App\Models\Block;
use App\Models\BlockAsset;
use App\Models\BlockButtonTranslation;
use App\Models\BlockContactFormTranslation;
use App\Models\BlockImageTranslation;
use App\Models\BlockTextTranslation;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Models\User;
use App\Support\Blocks\BlockTranslationWriter;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PageDuplicator
{
    public function __construct(
        private readonly BlockTranslationWriter $blockTranslationWriter,
        private readonly PageRevisionManager $revisionManager,
        private readonly PageDuplicateValidator $validator,
    ) {}

    public function duplicate(Page $page, Site $targetSite, User $actor, Collection $translations): PageDuplicateResult
    {
        $page->loadMissing([
            'site',
            'translations.locale',
            'slots.sharedSlot',
            'slots.slotType',
            'navigationItems',
        ]);

        $validation = $this->validator->validate($page, $targetSite, $translations);

        return DB::transaction(function () use ($page, $targetSite, $actor, $translations, $validation): PageDuplicateResult {
            $lockedPage = Page::query()
                ->with([
                    'site',
                    'translations.locale',
                    'slots.sharedSlot',
                    'slots.slotType',
                    'navigationItems',
                ])
                ->lockForUpdate()
                ->findOrFail($page->id);

            $lockedPage->translations()->lockForUpdate()->get();
            $lockedPage->slots()->lockForUpdate()->get();
            $blocks = Block::query()
                ->where('page_id', $lockedPage->id)
                ->with(['blockAssets', 'textTranslations', 'buttonTranslations', 'imageTranslations', 'contactFormTranslations'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $validation = $this->validator->validate($lockedPage, $targetSite, $translations);
            $defaultTranslation = $this->defaultTranslationPayload($lockedPage, $translations);

            $newPage = Page::query()->create([
                'site_id' => $targetSite->id,
                'title' => $defaultTranslation['name'],
                'slug' => $defaultTranslation['slug'],
                'page_type' => $lockedPage->page_type,
                'page_type_id' => $lockedPage->page_type_id,
                'layout_id' => $lockedPage->layout_id,
                'settings' => $lockedPage->settings,
                'status' => Page::STATUS_DRAFT,
                'published_at' => null,
                'review_requested_at' => null,
            ]);

            $newPage->translations()->delete();

            foreach ($translations as $translation) {
                PageTranslation::query()->create([
                    'page_id' => $newPage->id,
                    'site_id' => $targetSite->id,
                    'locale_id' => $translation['locale_id'],
                    'name' => $translation['name'],
                    'slug' => $translation['slug'],
                    'path' => $translation['path'],
                ]);
            }

            foreach ($lockedPage->slots as $slot) {
                PageSlot::query()->create([
                    'page_id' => $newPage->id,
                    'slot_type_id' => $slot->slot_type_id,
                    'source_type' => $slot->runtimeSourceType(),
                    'shared_slot_id' => $validation->sharedSlotRemaps[$slot->id] ?? null,
                    'sort_order' => $slot->sort_order,
                    'settings' => PageSlot::sanitizeSettings($slot->settings),
                ]);
            }

            $blockMap = [];

            foreach ($blocks as $block) {
                $attributes = Arr::except($block->getAttributes(), ['id', 'parent_id', 'page_id', 'created_at', 'updated_at']);
                $attributes['page_id'] = $newPage->id;
                $attributes['parent_id'] = null;

                $newBlock = Block::query()->create($attributes);
                $blockMap[$block->id] = $newBlock->id;

                foreach ($block->blockAssets as $blockAsset) {
                    BlockAsset::query()->create([
                        'block_id' => $newBlock->id,
                        'asset_id' => $blockAsset->asset_id,
                        'role' => $blockAsset->role,
                        'position' => $blockAsset->position,
                    ]);
                }

                $this->cloneBlockTranslations($block, $newBlock);

                if ($this->clonedBlockHasTranslationRows($newBlock)) {
                    $this->blockTranslationWriter->normalizeCanonicalStorage($newBlock->fresh([
                        'textTranslations',
                        'buttonTranslations',
                        'imageTranslations',
                        'contactFormTranslations',
                    ]));
                }
            }

            foreach ($blocks as $block) {
                if (! $block->parent_id || ! isset($blockMap[$block->id], $blockMap[$block->parent_id])) {
                    continue;
                }

                Block::query()->whereKey($blockMap[$block->id])->update([
                    'parent_id' => $blockMap[$block->parent_id],
                ]);
            }

            $duplicatedPage = $newPage->fresh([
                'site',
                'translations.locale',
                'slots.sharedSlot',
                'slots.slotType',
                'blocks',
            ]);

            $this->revisionManager->capture(
                $duplicatedPage,
                $actor,
                'Page duplicated',
                'Page was duplicated from page #'.$lockedPage->id.' into site '.$targetSite->name.'.',
            );

            return new PageDuplicateResult(
                sourcePage: $lockedPage,
                page: $duplicatedPage->fresh(['site', 'translations.locale', 'slots.sharedSlot', 'slots.slotType']),
                targetSite: $targetSite,
                remappedSharedSlotCount: $validation->sharedSlotRemaps->count(),
                sourceNavigationCount: $validation->sourceNavigationCount,
            );
        });
    }

    private function defaultTranslationPayload(Page $page, Collection $translations): array
    {
        $defaultLocaleId = $page->defaultTranslation()?->locale_id ?? $page->translations->first()?->locale_id;

        return $translations->firstWhere('locale_id', $defaultLocaleId)
            ?? $translations->firstOrFail();
    }

    private function cloneBlockTranslations(Block $source, Block $target): void
    {
        foreach ($source->textTranslations as $translation) {
            BlockTextTranslation::query()->create([
                'block_id' => $target->id,
                'locale_id' => $translation->locale_id,
                'title' => $translation->title,
                'eyebrow' => $translation->eyebrow,
                'subtitle' => $translation->subtitle,
                'content' => $translation->content,
                'meta' => $translation->meta,
            ]);
        }

        foreach ($source->buttonTranslations as $translation) {
            BlockButtonTranslation::query()->create([
                'block_id' => $target->id,
                'locale_id' => $translation->locale_id,
                'title' => $translation->title,
            ]);
        }

        foreach ($source->imageTranslations as $translation) {
            BlockImageTranslation::query()->create([
                'block_id' => $target->id,
                'locale_id' => $translation->locale_id,
                'caption' => $translation->caption,
                'alt_text' => $translation->alt_text,
            ]);
        }

        foreach ($source->contactFormTranslations as $translation) {
            BlockContactFormTranslation::query()->create([
                'block_id' => $target->id,
                'locale_id' => $translation->locale_id,
                'title' => $translation->title,
                'content' => $translation->content,
                'submit_label' => $translation->submit_label,
                'success_message' => $translation->success_message,
            ]);
        }
    }

    private function clonedBlockHasTranslationRows(Block $block): bool
    {
        return $block->textTranslations()->exists()
            || $block->buttonTranslations()->exists()
            || $block->imageTranslations()->exists()
            || $block->contactFormTranslations()->exists();
    }
}
