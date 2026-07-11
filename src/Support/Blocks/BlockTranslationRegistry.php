<?php

namespace WebBlocks\Cms\Support\Blocks;

use WebBlocks\Cms\Models\Block;

class BlockTranslationRegistry
{
  public function familyFor(Block|string|null $block): ?string
  {
    $slug = $block instanceof Block ? $block->typeSlug() : $block;

    return match ($slug) {
      'header', 'plain_text', 'rich-text', 'code', 'table', 'quote', 'html', 'content_header', 'hero', 'columns', 'column_item', 'feature-grid', 'feature-item', 'button_link', 'stat-card', 'gallery', 'download', 'file', 'video', 'audio', 'alert', 'cta', 'link-list', 'link-list-item', 'navbar-brand', 'sidebar-brand', 'sidebar-navigation', 'sidebar-nav-item', 'sidebar-nav-group', 'sidebar-footer', 'search-form' => 'text',
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
      'rich-text',
      'code',
      'table',
      'quote',
      'html',
      'content_header',
      'hero',
      'columns',
      'column_item',
      'feature-grid',
      'feature-item',
      'button_link',
      'stat-card',
      'gallery',
      'download',
      'file',
      'video',
      'audio',
      'alert',
      'cta',
      'link-list',
      'link-list-item',
      'sidebar-brand',
      'sidebar-navigation',
      'sidebar-nav-item',
      'sidebar-nav-group',
      'sidebar-footer',
      'search-form',
      'navbar-brand',
      'button',
      'image',
      'contact_form',
    ];
  }

  public function isTranslatable(Block|string|null $block): bool
  {
    return $this->familyFor($block) !== null || $this->supportsImageTranslations($block);
  }

  public function supportsTextTranslations(Block|string|null $block): bool
  {
    return $this->familyFor($block) === 'text';
  }

  public function supportsImageTranslations(Block|string|null $block): bool
  {
    $slug = $block instanceof Block ? $block->typeSlug() : $block;

    return in_array($slug, ['image'], true);
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
