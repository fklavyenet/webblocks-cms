<?php

namespace App\Support\Blocks;

use App\Models\Block;

class BlockTranslationRegistry
{
    public function familyFor(Block|string|null $block): ?string
    {
        $slug = $block instanceof Block ? $block->typeSlug() : $block;

        return match ($slug) {
            'heading', 'text', 'rich-text', 'html', 'section', 'columns', 'column_item', 'callout', 'quote', 'faq', 'tabs' => 'text',
            'button' => 'button',
            'image' => 'image',
            'contact_form' => 'contact_form',
            default => null,
        };
    }

    public function supportedTypes(): array
    {
        return [
            'heading',
            'text',
            'rich-text',
            'html',
            'section',
            'columns',
            'column_item',
            'callout',
            'quote',
            'faq',
            'tabs',
            'button',
            'image',
            'contact_form',
        ];
    }

    public function isTranslatable(Block|string|null $block): bool
    {
        return $this->familyFor($block) !== null;
    }

    public function translatedFieldMap(string $family): array
    {
        return match ($family) {
            'text' => ['title', 'subtitle', 'content'],
            'button' => ['title'],
            'image' => ['caption', 'alt_text'],
            'contact_form' => ['title', 'content', 'submit_label', 'success_message'],
            default => [],
        };
    }
}
