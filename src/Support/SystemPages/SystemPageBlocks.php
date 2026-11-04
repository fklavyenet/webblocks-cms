<?php

namespace WebBlocks\Cms\Support\SystemPages;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Support\Locales\LocaleResolver;
use WebBlocks\Cms\Support\Pages\PageRouteResolver;
use WebBlocks\Cms\Support\Pages\PublicMount;
use WebBlocks\Cms\Support\Translations\CmsTranslator;

/** PHP declarations are creation recipes; ordinary SQL blocks own saved content. */
final class SystemPageBlocks
{
  private function recipes(SystemPageDefinition $definition): array
  {
    return $definition->blocks ?: [[
      'type' => 'hero', 'translations' => [
        'title' => $definition->labelKey,
        'content' => 'webblocks-cms::system_pages.feature_intro',
      ],
    ]];
  }

  /** Called inside the initial page-creation transaction, before its first revision. */
  public function seed(Page $page, SystemPageDefinition $definition): void
  {
    $main = $page->slots()->whereHas('slotType', fn ($query) => $query->where('slug', 'main'))->firstOrFail();
    $locales = app(LocaleResolver::class)->enabledForSite($page->site);
    foreach ($this->recipes($definition) as $position => $recipe) {
      $this->save($page, $main, $recipe, $position, $locales);
    }

  }

  private function save(Page $page, PageSlot $slot, array $recipe, int $position, Collection $locales, ?Block $parent = null): void
  {
    // Defaults use native translated editorial blocks, never raw HTML or plugin state.
    $type = $this->type($recipe);
    $block = Block::create([
      'page_id' => $page->id, 'slot_type_id' => $slot->slot_type_id,
      'block_type_id' => $type->id, 'type' => $type->slug, 'parent_id' => $parent?->id,
      'sort_order' => $position, 'status' => Page::STATUS_DRAFT,
      'variant' => $recipe['variant'] ?? null, 'settings' => $this->settings($page, $recipe),
    ]);
    foreach ($locales as $locale) {
      $block->textTranslations()->create(['locale_id' => $locale->id, ...$this->texts($recipe, $locale->code)]);
    }
    foreach ($recipe['children'] ?? [] as $childPosition => $child) {
      if (! $block->canAcceptChildType($child['type'] ?? null)) {
        throw new InvalidArgumentException('Unsupported child in System Page defaults.');
      }
      $this->save($page, $slot, $child, $childPosition, $locales, $block);
    }
  }

  private function type(array $recipe): BlockType
  {
    if (! in_array($recipe['type'] ?? null, ['hero', 'header', 'plain_text', 'button_link'], true)) {
      throw new InvalidArgumentException('System Page defaults require native editorial blocks.');
    }

    return BlockType::query()->where('slug', $recipe['type'])->where('status', 'published')->firstOrFail();
  }

  private function settings(Page $page, array $recipe): array
  {
    $settings = $recipe['settings'] ?? [];
    if (($settings['url'] ?? null) === '/') {
      $settings['url'] = app(PageRouteResolver::class)->homePath(null, $page->site) ?? app(PublicMount::class)->path('/');
    }

    return $settings;
  }

  private function texts(array $recipe, string $locale): array
  {
    $texts = [];
    foreach ($recipe['translations'] ?? [] as $field => $key) {
      if (! in_array($field, ['title', 'subtitle', 'eyebrow', 'content'], true) || ! is_string($key)) {
        throw new InvalidArgumentException('Invalid System Page default translation.');
      }
      if (! str_contains($key, '::')) {
        // Legacy plugin declarations could use a literal label before recipes existed.
        $texts[$field] = __($key, [], $locale);

        continue;
      }
      [$namespace, $path] = explode('::', $key, 2);
      $translator = app(CmsTranslator::class);
      $texts[$field] = $namespace === 'webblocks-cms' ? $translator->get($path, $locale) : $translator->plugin($namespace, $path, $locale);
    }

    return $texts;
  }
}
