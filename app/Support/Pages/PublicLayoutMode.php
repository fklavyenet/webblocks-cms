<?php

namespace App\Support\Pages;

use App\Models\Page;

class PublicLayoutMode
{
    public const STACK = 'stack';

    public const CONTENT = 'content';

    public const SIDEBAR = 'sidebar';

    public static function forPage(Page $page): string
    {
        if ($page->publicShellPreset() === 'docs') {
            return self::SIDEBAR;
        }

        if (self::hasPopulatedSidebarSlot($page)) {
            return self::SIDEBAR;
        }

        if (self::hasReliableContentShellMetadata($page)) {
            return self::CONTENT;
        }

        return self::STACK;
    }

    private static function hasPopulatedSidebarSlot(Page $page): bool
    {
        $slots = $page->relationLoaded('slots')
            ? $page->slots
            : $page->slots()->with('slotType')->get();

        $sidebarSlotTypeIds = $slots
            ->filter(fn ($slot) => ($slot->slotType?->slug ?? 'main') === 'sidebar')
            ->pluck('slot_type_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($sidebarSlotTypeIds === []) {
            return false;
        }

        $blocks = $page->relationLoaded('blocks')
            ? $page->blocks
            : $page->blocks()->where('status', 'published')->get();

        return $blocks
            ->filter(fn ($block) => $block->status === 'published' && $block->parent_id === null)
            ->contains(fn ($block) => $block->slot === 'sidebar' || in_array((int) $block->slot_type_id, $sidebarSlotTypeIds, true));
    }

    private static function hasReliableContentShellMetadata(Page $page): bool
    {
        // TODO: Enable content mode only after page type or layout metadata explicitly models
        // docs/editorial/content-shell intent. Current values such as default, page, landing,
        // blog, and sidebar-* are not specific enough to safely force wb-content-shell.
        return false;
    }
}
