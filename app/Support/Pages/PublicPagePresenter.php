<?php

namespace App\Support\Pages;

use App\Models\Block;
use App\Models\Page;
use App\Models\PageSlot;
use App\Support\Blocks\BlockTranslationResolver;
use Illuminate\Support\Collection;

class PublicPagePresenter
{
    private const SHELL_DEFAULT = 'default';

    private const SHELL_DOCS = 'docs';

    public function __construct(
        private readonly BlockTranslationResolver $blockTranslationResolver,
    ) {}

    public function present(Page $page): array
    {
        $topLevelBlocks = $page->blocks
            ->whereNull('parent_id')
            ->where('status', 'published')
            ->sortBy(fn (Block $block) => sprintf('%010d-%010d', (int) $block->sort_order, (int) $block->id))
            ->values();

        $translatedTopLevelBlocks = $this->blockTranslationResolver
            ->resolveCollection($topLevelBlocks)
            ->values();

        $slots = $page->slots
            ->sortBy(fn (PageSlot $slot) => sprintf('%010d-%010d', (int) $slot->sort_order, (int) $slot->id))
            ->map(fn (PageSlot $slot) => $this->presentSlot($slot, $translatedTopLevelBlocks))
            ->values();

        return [
            'page' => $page,
            'publicShell' => $this->presentShell($page, $slots),
            'slots' => $slots,
            'metaDescription' => $this->resolveMetaDescription($page, $translatedTopLevelBlocks),
        ];
    }

    private function presentSlot(PageSlot $slot, Collection $topLevelBlocks): array
    {
        $slug = $slot->slotType?->slug ?? 'main';
        $blocks = $topLevelBlocks
            ->where('slot_type_id', $slot->slot_type_id)
            ->values();
        $settings = is_array($slot->settings) ? $slot->settings : [];
        $defaultElement = PageSlot::defaultWrapperElementForSlug($slug);
        $preset = $slot->wrapperPreset();
        $configuredElement = strtolower((string) ($settings['wrapper_element'] ?? ''));
        $element = match ($preset) {
            'docs-navbar' => 'header',
            'docs-sidebar' => 'aside',
            'docs-main' => 'main',
            default => in_array($configuredElement, PageSlot::allowedWrapperElements(), true) ? $configuredElement : $defaultElement,
        };
        $class = match ($preset) {
            'docs-navbar' => 'wb-navbar wb-navbar-glass',
            'docs-sidebar' => 'wb-sidebar',
            'docs-main' => 'wb-content-shell wb-docs-main',
            default => '',
        };

        return [
            'slug' => $slug,
            'name' => $slot->slotType?->name ?? str($slug)->headline()->toString(),
            'blocks' => $blocks,
            'wrapper' => [
                'element' => in_array($element, PageSlot::allowedWrapperElements(), true) ? $element : $defaultElement,
                'class' => $class,
                'preset' => $preset,
                'settings' => $settings,
                'body_class' => $preset === 'docs-navbar' ? 'wb-docs-topbar wb-flex wb-items-center wb-justify-between wb-gap-3 wb-w-full' : '',
            ],
        ];
    }

    private function presentShell(Page $page, Collection $slots): array
    {
        $preset = $page->publicShellPreset();

        if ($preset !== self::SHELL_DOCS) {
            return [
                'preset' => self::SHELL_DEFAULT,
                'slots' => $slots->values(),
            ];
        }

        $header = $slots->firstWhere('slug', 'header');
        $main = $slots->firstWhere('slug', 'main');
        $sidebar = $slots->firstWhere('slug', 'sidebar');
        $footer = $slots->firstWhere('slug', 'footer');
        $remaining = $slots->reject(fn (array $slot) => in_array($slot['slug'], ['header', 'main', 'sidebar', 'footer'], true))->values();

        return [
            'preset' => self::SHELL_DOCS,
            'header' => $header,
            'main' => $main,
            'sidebar' => $sidebar,
            'footer' => $footer,
            'content_slots' => collect([$main, $sidebar])
                ->filter()
                ->concat($remaining)
                ->values(),
            'slots' => $slots->values(),
        ];
    }

    private function resolveMetaDescription(Page $page, Collection $blocks): ?string
    {
        $summary = $blocks
            ->map(fn (Block $block) => $block->content ?: $block->subtitle)
            ->filter()
            ->first();

        return $summary ? str(strip_tags((string) $summary))->squish()->limit(160)->toString() : config('app.slogan');
    }
}
