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
  public function recipes(SystemPageDefinition $definition): array
  {
    return $definition->blocks ?: [[
      'type' => 'hero', 'translations' => [
        'title' => $definition->labelKey,
        'content' => 'webblocks-cms::system_pages.feature_intro',
      ],
    ]];
  }

  public function canAdd(Page $page): bool
  {
    if (! $page->isSystemPage() || $page->status !== Page::STATUS_DRAFT) {
      return false;
    }
    $main = $page->slots()->whereHas('slotType', fn ($query) => $query->where('slug', 'main'))->first();

    return $main && $main->runtimeSourceType() === PageSlot::SOURCE_TYPE_PAGE
      && ! $page->blocks()->where('slot_type_id', $main->slot_type_id)->exists();
  }

  public function add(Page $page, SystemPageDefinition $definition): bool
  {
    if (! $this->canAdd($page)) {
      return false;
    }
    $main = $page->slots()->whereHas('slotType', fn ($query) => $query->where('slug', 'main'))->firstOrFail();
    $locales = app(LocaleResolver::class)->enabledForSite($page->site);
    foreach ($this->recipes($definition) as $position => $recipe) {
      $this->save($page, $main, $recipe, $position, $locales);
    }

    return true;
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

  /** A legacy empty preview uses unsaved native blocks, never a database write. */
  public function sample(Page $page, SystemPageDefinition $definition, string $locale): Collection
  {
    return collect($this->recipes($definition))->map(fn ($recipe) => $this->sampleBlock($page, $recipe, $locale));
  }

  private function sampleBlock(Page $page, array $recipe, string $locale): Block
  {
    $type = $this->type($recipe);
    $block = new Block([
      'type' => $type->slug, 'page_id' => $page->id, 'slot' => 'main',
      'variant' => $recipe['variant'] ?? null, 'settings' => $this->settings($page, $recipe, $locale),
      ...$this->texts($recipe, $locale),
    ]);
    $block->setRelation('blockType', $type)->setRelation('renderPage', $page)->setRelation('page', $page);
    $block->setAttribute('render_locale_code', $locale);
    $block->setAttribute('requested_locale_code', $locale);
    $block->setAttribute('render_preview', true);
    $block->setRelation('children', collect($recipe['children'] ?? [])->map(fn ($child) => $this->sampleBlock($page, $child, $locale)));

    return $block;
  }

  private function type(array $recipe): BlockType
  {
    if (! in_array($recipe['type'] ?? null, ['hero', 'header', 'plain_text', 'button_link'], true)) {
      throw new InvalidArgumentException('System Page defaults require native editorial blocks.');
    }

    return BlockType::query()->where('slug', $recipe['type'])->where('status', 'published')->firstOrFail();
  }

  private function settings(Page $page, array $recipe, ?string $locale = null): array
  {
    $settings = $recipe['settings'] ?? [];
    if (($settings['url'] ?? null) === '/') {
      $settings['url'] = app(PageRouteResolver::class)->homePath($locale, $page->site) ?? app(PublicMount::class)->path('/');
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
