<?php

namespace App\Support\Search;

use App\Models\Block;
use Illuminate\Support\Facades\Log;

class BlockSearchTextExtractorRegistry
{
    public function __construct(
        private readonly SearchTextNormalizer $normalizer,
    ) {}

    public function extract(Block $block): string
    {
        $slug = $block->typeSlug();

        return match ($slug) {
            'header', 'content_header', 'plain_text', 'text', 'rich-text', 'callout', 'quote', 'card', 'stat-card', 'alert', 'faq', 'faq-list' => $this->fields($block, ['title', 'eyebrow', 'subtitle', 'content', 'meta']),
            'code' => $this->fields($block, ['title', 'content']),
            'table' => $this->table($block),
            'list' => $this->list($block),
            'link-list' => $this->fields($block, ['title', 'subtitle', 'content']),
            'link-list-item' => $this->fields($block, ['title', 'subtitle', 'content']),
            'accordion' => $this->fields($block, ['title', 'content']),
            'button', 'button_link', 'search-form' => $this->fields($block, ['title', 'subtitle', 'content']),
            'section', 'container', 'cluster', 'grid', 'columns', 'column_item', 'feature-grid', 'feature-item', 'cta' => $this->fields($block, ['title', 'subtitle', 'content', 'meta']),
            'breadcrumb', 'header-actions', 'navigation-auto', 'menu', 'sidebar-navigation', 'sidebar-nav-item', 'sidebar-nav-group', 'sticky-navbar' => '',
            default => $this->fallback($block),
        };
    }

    private function table(Block $block): string
    {
        $settingsRows = collect($block->setting('rows', []))
            ->flatMap(function ($row) {
                return collect($row['columns'] ?? $row)
                    ->map(fn ($cell) => is_array($cell) ? ($cell['label'] ?? '') : $cell)
                    ->all();
            })
            ->all();

        return $this->normalizer->join([
            $block->title,
            $block->content,
            $settingsRows,
        ]);
    }

    private function list(Block $block): string
    {
        $items = collect($block->setting('items', []))
            ->map(fn ($item) => is_array($item) ? ($item['label'] ?? $item['title'] ?? '') : $item)
            ->all();

        return $this->normalizer->join([
            $block->title,
            $block->content,
            $items,
        ]);
    }

    private function fallback(Block $block): string
    {
        if (app()->environment(['local', 'development', 'testing'])) {
            Log::debug('Search extractor falling back to generic field extraction.', [
                'block_id' => $block->id,
                'type' => $block->typeSlug(),
            ]);
        }

        return $this->fields($block, ['title', 'eyebrow', 'subtitle', 'content', 'meta']);
    }

    private function fields(Block $block, array $fields): string
    {
        return $this->normalizer->join(array_map(
            fn (string $field) => $block->getAttribute($field),
            $fields,
        ));
    }
}
