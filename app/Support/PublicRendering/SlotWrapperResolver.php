<?php

namespace App\Support\PublicRendering;

use App\Models\Page;
use App\Models\PageSlot;

class SlotWrapperResolver
{
    public function resolve(Page $page, PageSlot $slot): array
    {
        $shell = $page->publicShellPreset();
        $slug = $this->normalizeSlotSlug($slot->slotType?->slug);
        $mapping = $shell === 'docs'
            ? $this->resolveDocsMapping($slug)
            : $this->resolveDefaultMapping($slug);
        $attributes = [
            'data-wb-slot' => $slug,
        ];

        if ($slug === 'main') {
            $attributes['id'] = 'main-content';
        }

        if ($mapping['preset'] === 'docs-sidebar') {
            $attributes['id'] = 'docsSidebar';
        }

        if ($mapping['class'] !== null) {
            $attributes['class'] = $mapping['class'];
        }

        return [
            'preset' => $mapping['preset'],
            'element' => $mapping['element'],
            'attributes' => $attributes,
        ];
    }

    private function normalizeSlotSlug(?string $slug): string
    {
        $normalized = strtolower(trim((string) $slug));

        return $normalized !== '' ? $normalized : 'main';
    }

    private function resolveDefaultMapping(string $slug): array
    {
        return match ($slug) {
            'header' => ['preset' => 'default', 'element' => 'header', 'class' => null],
            'main' => ['preset' => 'default', 'element' => 'main', 'class' => null],
            'sidebar' => ['preset' => 'default', 'element' => 'aside', 'class' => null],
            'footer' => ['preset' => 'default', 'element' => 'footer', 'class' => null],
            default => ['preset' => 'default', 'element' => 'div', 'class' => null],
        };
    }

    private function resolveDocsMapping(string $slug): array
    {
        return match ($slug) {
            'header' => ['preset' => 'docs-navbar', 'element' => 'nav', 'class' => 'wb-navbar wb-navbar-glass wb-w-full'],
            'sidebar' => ['preset' => 'docs-sidebar', 'element' => 'aside', 'class' => 'wb-sidebar'],
            'main' => ['preset' => 'docs-main', 'element' => 'main', 'class' => 'wb-dashboard-main'],
            'footer' => ['preset' => 'default', 'element' => 'footer', 'class' => null],
            default => ['preset' => 'default', 'element' => 'div', 'class' => null],
        };
    }
}
