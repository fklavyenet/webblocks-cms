<?php

namespace App\Support\PublicRendering;

use App\Models\Page;
use App\Models\PageSlot;

class SlotWrapperResolver
{
    public function resolve(Page $page, PageSlot $slot): array
    {
        $slug = $slot->slotType?->slug ?? 'main';
        $preset = $this->resolvePreset($page, $slot, $slug);
        $element = $this->resolveElement($slot, $slug);
        $attributes = [
            'data-wb-slot' => $slug,
        ];

        if ($slug === 'main') {
            $attributes['id'] = 'main-content';
        }

        if ($preset === 'docs-navbar') {
            $attributes['class'] = 'wb-navbar wb-navbar-glass wb-w-full';
        }

        if ($preset === 'docs-sidebar') {
            $attributes['id'] = 'docsSidebar';
            $attributes['class'] = 'wb-sidebar';
        }

        if ($preset === 'docs-main') {
            $attributes['class'] = 'wb-dashboard-main';
        }

        return [
            'preset' => $preset,
            'element' => $element,
            'attributes' => $attributes,
        ];
    }

    private function resolvePreset(Page $page, PageSlot $slot, string $slug): string
    {
        $preset = $slot->wrapperPreset();

        if ($preset !== 'default') {
            return $preset;
        }

        if ($page->publicShellPreset() !== 'docs') {
            return 'default';
        }

        return match ($slug) {
            'header' => 'docs-navbar',
            'sidebar' => 'docs-sidebar',
            'main' => 'docs-main',
            default => 'default',
        };
    }

    private function resolveElement(PageSlot $slot, string $slug): string
    {
        return $slot->wrapperElement() ?? PageSlot::defaultWrapperElementForSlug($slug);
    }
}
