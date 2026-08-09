<?php

namespace WebBlocks\Cms\Support\Blocks;

use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\Plugins\PluginBlockCatalog;

class BlockTranslationRegistry
{
  /**
   * The family a plugin-declared block belongs to.
   *
   * Core families are tables of columns, because core knows every field before the
   * migration is written. A plugin's block is declared at install time by a package
   * core has never seen, so it gets one family backed by rows instead — the fields
   * are data, and which fields exist is the plugin's answer rather than this file's.
   */
  public const PLUGIN_FAMILY = 'plugin';

  public function familyFor(Block|string|null $block): ?string
  {
    $slug = $block instanceof Block ? $block->typeSlug() : $block;

    if ($slug !== null && $this->pluginFieldsFor($slug) !== []) {
      return self::PLUGIN_FAMILY;
    }

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

  /**
   * The translated field names for one block, whichever family it belongs to.
   *
   * `translatedFieldMap()` answers per family, which is enough while every family is
   * a fixed set. The plugin family is not: two plugin blocks in the same family have
   * different fields, so anything that needs the actual list has to ask about the
   * block rather than about the family.
   *
   * @return list<string>
   */
  public function translatedFieldsFor(Block|string|null $block): array
  {
    $family = $this->familyFor($block);

    if ($family === null) {
      return [];
    }

    if ($family !== self::PLUGIN_FAMILY) {
      return $this->translatedFieldMap($family);
    }

    return $this->pluginFieldsFor($block instanceof Block ? $block->typeSlug() : $block);
  }

  /**
   * What a plugin declared as translatable for this catalog slug.
   *
   * Only enabled plugins answer. A disabled plugin contributes nothing anywhere else
   * either, and a block whose plugin is off must not start reporting a family whose
   * storage nothing will read.
   *
   * @return list<string>
   */
  private function pluginFieldsFor(?string $slug): array
  {
    if ($slug === null || $slug === '') {
      return [];
    }

    $catalog = app(PluginBlockCatalog::class);

    if (! $catalog->isPluginCatalogSlug($slug)) {
      return [];
    }

    return $catalog->enabledDefinitionForCatalogSlug($slug)?->translatedFieldNames() ?? [];
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
      'contact_form' => ['title', 'content', 'submit_label', 'success_message', 'consent_label'],
      default => [],
    };
  }
}
