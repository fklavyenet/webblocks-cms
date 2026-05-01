<?php

namespace App\Support\Blocks;

use App\Models\Block;

class BlockTranslationRegistry
{
    public function familyFor(Block|string|null $block): ?string
    {
        $slug = $block instanceof Block ? $block->typeSlug() : $block;

        return match ($slug) {
            'header', 'plain_text', 'content_header', 'button_link', 'card', 'stat-card', 'alert', 'link-list', 'link-list-item', 'sidebar-brand', 'sidebar-navigation', 'sidebar-nav-item', 'sidebar-nav-group', 'sidebar-footer' => 'text',
            'button' => 'button',
            'image' => 'image',
            'contact_form' => 'contact_form',
            default => null,
        };
    }

    public function supportedTypes(): array
    {
        return [
            'header',
            'plain_text',
            'content_header',
            'button_link',
            'card',
            'stat-card',
            'alert',
            'link-list',
            'link-list-item',
            'sidebar-brand',
            'sidebar-navigation',
            'sidebar-nav-item',
            'sidebar-nav-group',
            'sidebar-footer',
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
            'text' => ['title', 'eyebrow', 'subtitle', 'content', 'meta'],
            'button' => ['title'],
            'image' => ['caption', 'alt_text'],
            'contact_form' => ['title', 'content', 'submit_label', 'success_message'],
            default => [],
        };
    }
}
