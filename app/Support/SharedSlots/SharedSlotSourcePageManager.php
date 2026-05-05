<?php

namespace App\Support\SharedSlots;

use App\Models\Block;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\SharedSlot;
use App\Models\SharedSlotBlock;
use App\Models\SlotType;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class SharedSlotSourcePageManager
{
    public function findFor(SharedSlot $sharedSlot): ?Page
    {
        return Page::query()
            ->where('page_type', Page::TYPE_SHARED_SLOT_SOURCE)
            ->where('settings->shared_slot_id', $sharedSlot->id)
            ->first();
    }

    public function ensureFor(SharedSlot $sharedSlot): Page
    {
        $page = $this->findFor($sharedSlot) ?? new Page;
        $settings = is_array($page->settings) ? $page->settings : [];

        $page->fill([
            'site_id' => $sharedSlot->site_id,
            'title' => $this->titleFor($sharedSlot),
            'slug' => $this->slugFor($sharedSlot),
            'page_type' => Page::TYPE_SHARED_SLOT_SOURCE,
            'status' => Page::STATUS_DRAFT,
            'settings' => array_merge($settings, [
                'public_shell' => Page::normalizePublicShellPreset($sharedSlot->public_shell ?: 'default'),
                'shared_slot_id' => $sharedSlot->id,
                'shared_slot_handle' => $sharedSlot->handle,
            ]),
        ]);
        $page->save();

        $slotType = $this->editorSlotTypeFor($sharedSlot);

        $pageSlot = $page->slots()->first();

        if (! $pageSlot) {
            $pageSlot = $page->slots()->create([
                'slot_type_id' => $slotType->id,
                'source_type' => PageSlot::SOURCE_TYPE_PAGE,
                'sort_order' => 0,
            ]);
        } elseif ((int) $pageSlot->slot_type_id !== (int) $slotType->id) {
            $pageSlot->update([
                'slot_type_id' => $slotType->id,
                'source_type' => PageSlot::SOURCE_TYPE_PAGE,
            ]);
        }

        $page->slots()
            ->whereKeyNot($pageSlot->id)
            ->delete();

        Block::query()
            ->where('page_id', $page->id)
            ->where(function ($query) use ($slotType): void {
                $query->where('slot_type_id', '!=', $slotType->id)
                    ->orWhere('slot', '!=', $slotType->slug);
            })
            ->update([
                'slot_type_id' => $slotType->id,
                'slot' => $slotType->slug,
            ]);

        return $page->fresh(['site', 'slots.slotType', 'translations']);
    }

    public function deleteFor(SharedSlot $sharedSlot): void
    {
        $page = $this->findFor($sharedSlot);

        if ($page) {
            $page->delete();
        }
    }

    public function editorSlotTypeFor(SharedSlot $sharedSlot): SlotType
    {
        $requestedSlug = trim((string) ($sharedSlot->slot_name ?? ''));
        $query = SlotType::query()->where('status', 'published');

        if ($requestedSlug !== '') {
            $match = (clone $query)->where('slug', $requestedSlug)->first();

            if ($match) {
                return $match;
            }
        }

        return (clone $query)->where('slug', 'main')->first()
            ?? $query->orderBy('sort_order')->orderBy('name')->firstOrFail();
    }

    public function rebuildAssignments(SharedSlot $sharedSlot): void
    {
        $page = $this->ensureFor($sharedSlot);
        $blocks = $page->blocks()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'parent_id', 'sort_order']);
        $childrenByParent = $blocks->groupBy('parent_id');

        DB::transaction(function () use ($sharedSlot, $childrenByParent): void {
            $sharedSlot->slotBlocks()->delete();

            $persistBranch = function (?int $parentBlockId, ?int $parentAssignmentId = null) use (&$persistBranch, $sharedSlot, $childrenByParent): void {
                $siblings = $childrenByParent->get($parentBlockId, collect())
                    ->sortBy(fn (Block $block) => sprintf('%010d-%010d', (int) $block->sort_order, (int) $block->id))
                    ->values();

                foreach ($siblings as $index => $block) {
                    $assignment = SharedSlotBlock::query()->create([
                        'shared_slot_id' => $sharedSlot->id,
                        'block_id' => $block->id,
                        'parent_id' => $parentAssignmentId,
                        'sort_order' => $index,
                    ]);

                    $persistBranch($block->id, $assignment->id);
                }
            };

            $persistBranch(null);
        });
    }

    public function sourceBlocks(SharedSlot $sharedSlot): Collection
    {
        return $this->ensureFor($sharedSlot)
            ->blocks()
            ->with([
                'blockType',
                'slotType',
                'blockAssets.asset',
                'textTranslations',
                'buttonTranslations',
                'imageTranslations',
                'contactFormTranslations',
                'children' => fn ($query) => $query
                    ->with([
                        'blockType',
                        'slotType',
                        'blockAssets.asset',
                        'textTranslations',
                        'buttonTranslations',
                        'imageTranslations',
                        'contactFormTranslations',
                        'children' => fn ($nested) => $nested->with([
                            'blockType',
                            'slotType',
                            'blockAssets.asset',
                            'textTranslations',
                            'buttonTranslations',
                            'imageTranslations',
                            'contactFormTranslations',
                        ])->orderBy('sort_order'),
                    ])
                    ->orderBy('sort_order'),
            ])
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();
    }

    private function titleFor(SharedSlot $sharedSlot): string
    {
        return 'Shared Slot Source: '.$sharedSlot->name;
    }

    private function slugFor(SharedSlot $sharedSlot): string
    {
        return 'shared-slot-'.$sharedSlot->handle;
    }
}
