<?php

namespace WebBlocks\Cms\Support\PublicRendering;

use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayoutSlot;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Support\Pages\LayoutMarkup;
use WebBlocks\Cms\Support\Pages\PageLayoutManager;

class SlotWrapperResolver
{
    public function __construct(
        private readonly PageLayoutManager $pageLayouts,
    ) {}

    public function resolve(Page $page, PageSlot $slot): array
    {
        $slug = $this->normalizeSlotSlug($slot->slotType?->slug);
        $managedSlot = $this->pageLayouts->slotDefinitionForPageSlot($page, $slug);
        $mapping = $managedSlot instanceof PageLayoutSlot
            ? $this->resolveManagedMapping($managedSlot, $slug)
            : $this->resolveLegacyMapping($page, $slug);
        $attributes = [
            'data-wb-slot' => $slug,
        ];

        if ($mapping['id'] !== null) {
            $attributes['id'] = $mapping['id'];
        }

        if ($mapping['class'] !== null) {
            $attributes['class'] = $mapping['class'];
        }

        return [
            'preset' => $mapping['preset'],
            'element' => $mapping['element'],
            'attributes' => $attributes,
            'before_html' => $mapping['before_html'],
            'start_html' => $mapping['start_html'],
            'end_html' => $mapping['end_html'],
            'after_html' => $mapping['after_html'],
        ];
    }

    private function normalizeSlotSlug(?string $slug): string
    {
        $normalized = strtolower(trim((string) $slug));

        return $normalized !== '' ? $normalized : 'main';
    }

    private function resolveManagedMapping(PageLayoutSlot $slot, string $slug): array
    {
        $element = LayoutMarkup::normalizeElement($slot->html_element);

        return [
            'preset' => $this->presetFor($slug, $element, LayoutMarkup::normalizeTokenList($slot->html_classes)),
            'element' => $element,
            'id' => LayoutMarkup::normalizeHtmlId($slot->html_id),
            'class' => LayoutMarkup::normalizeTokenList($slot->html_classes),
            'before_html' => trim((string) ($slot->before_html ?? '')) ?: null,
            'start_html' => trim((string) ($slot->start_html ?? '')) ?: null,
            'end_html' => trim((string) ($slot->end_html ?? '')) ?: null,
            'after_html' => trim((string) ($slot->after_html ?? '')) ?: null,
        ];
    }

    private function resolveLegacyMapping(Page $page, string $slug): array
    {
        return $page->resolvedPublicShellType() === 'docs'
            ? $this->resolveDocsMapping($slug)
            : $this->resolveDefaultMapping($slug);
    }

    private function resolveDefaultMapping(string $slug): array
    {
        return match ($slug) {
            'header' => ['preset' => 'default', 'element' => 'header', 'id' => null, 'class' => 'wb-public-site-header', 'before_html' => null, 'start_html' => null, 'end_html' => null, 'after_html' => null],
            'main' => ['preset' => 'default', 'element' => 'main', 'id' => 'main-content', 'class' => null, 'before_html' => null, 'start_html' => null, 'end_html' => null, 'after_html' => null],
            'sidebar' => ['preset' => 'default', 'element' => 'aside', 'id' => null, 'class' => null, 'before_html' => null, 'start_html' => null, 'end_html' => null, 'after_html' => null],
            'footer' => ['preset' => 'default', 'element' => 'footer', 'id' => null, 'class' => null, 'before_html' => null, 'start_html' => null, 'end_html' => null, 'after_html' => null],
            default => ['preset' => 'default', 'element' => 'div', 'id' => null, 'class' => null, 'before_html' => null, 'start_html' => null, 'end_html' => null, 'after_html' => null],
        };
    }

    private function resolveDocsMapping(string $slug): array
    {
        return match ($slug) {
            'header' => ['preset' => 'docs-navbar', 'element' => 'nav', 'id' => null, 'class' => 'wb-navbar wb-navbar-glass wb-w-full', 'before_html' => null, 'start_html' => null, 'end_html' => null, 'after_html' => null],
            'sidebar' => ['preset' => 'docs-sidebar', 'element' => 'aside', 'id' => 'docsSidebar', 'class' => 'wb-sidebar', 'before_html' => null, 'start_html' => null, 'end_html' => null, 'after_html' => null],
            'main' => ['preset' => 'docs-main', 'element' => 'main', 'id' => 'main-content', 'class' => 'wb-dashboard-main', 'before_html' => null, 'start_html' => null, 'end_html' => null, 'after_html' => null],
            'footer' => ['preset' => 'default', 'element' => 'footer', 'id' => null, 'class' => null, 'before_html' => null, 'start_html' => null, 'end_html' => null, 'after_html' => null],
            default => ['preset' => 'default', 'element' => 'div', 'id' => null, 'class' => null, 'before_html' => null, 'start_html' => null, 'end_html' => null, 'after_html' => null],
        };
    }

    private function presetFor(string $slug, string $element, ?string $classes): string
    {
        return match ($slug) {
            'header' => $element === 'nav' || str_contains((string) $classes, 'wb-navbar') ? 'docs-navbar' : 'default',
            'sidebar' => $element === 'aside' && str_contains((string) $classes, 'wb-sidebar') ? 'docs-sidebar' : 'default',
            'main' => $element === 'main' && str_contains((string) $classes, 'wb-dashboard-main') ? 'docs-main' : 'default',
            default => 'default',
        };
    }
}
