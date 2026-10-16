<?php

namespace WebBlocks\Cms\Support\ContentSources;

use Illuminate\Contracts\Auth\Authenticatable;
use WebBlocks\Cms\Models\AutomaticPageRule;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Support\Pages\PublicPagePresenter;

/** Renders a selected record in memory; never creates pages or rewrites bindings. */
final class AutomaticPagePreview
{
  public function __construct(private readonly AutomaticPageInspector $inspector, private readonly PublicPagePresenter $presenter) {}

  public function render(AutomaticPageRule $rule, string $key, string $locale, ?Authenticatable $actor, bool $public = false, ?Page $design = null): string
  {
    $inspection = $this->inspector->record($rule, $key, $public ? null : $actor, ! $public);
    abort_unless($inspection['status'] === 'available', 422, __('webblocks-cms::automatic-pages.preview_unavailable'));
    $page = $design ?? app(AutomaticPageDesign::class)->page($rule);
    abort_unless($page && (int) $page->site_id === (int) $rule->site_id, 404);
    abort_unless($page && ! $page->isSharedSlotSourcePage(), 422);
    $page->load(['site', 'translations.locale', 'slots.slotType', 'slots.sharedSlot', 'pageAssets',
      'blocks' => fn ($query) => $query->when($public, fn ($q) => $q->where('status', 'published'))->with($this->blockRelations($public))->orderBy('sort_order')->orderBy('id')]);
    $translation = $page->translations->first(fn ($item) => $item->locale?->code === $locale);
    abort_unless($translation && $rule->site->enabledLocales()->where('code', $locale)->exists(), 422);
    $page->setRelation('currentTranslation', $translation);
    $data = $this->presenter->present($page, ! $public, $locale);
    $config = $inspection['configuration'];
    $record = $inspection['records'][$locale];
    // The existing design stays intact in storage. Its in-memory identity must
    // follow the selected record as well, including head alternate links.
    $translations = $page->translations->map(function ($original) use ($rule, $inspection, $config) {
      $translated = clone $original;
      $translated->path = $rule->base_path.'/'.$inspection['slug'];
      $translated->slug = $inspection['slug'];
      $translated->name = data_get($inspection['records'][$original->locale->code] ?? [], $config['publication']['title_field'], $original->name);

      return $translated;
    });
    $page->setRelation('translations', $translations);
    $page->setRelation('currentTranslation', $translations->first(fn ($item) => $item->locale->code === $locale));
    $localeLinks = [];
    foreach ($rule->site->enabledLocales()->get() as $language) {
      $localeLinks[$language->code] = $public ? $this->inspector->url($rule, $inspection['slug'], $language->code) : route('admin.automatic-pages.preview', ['automaticPage' => $rule, 'record' => $key, 'locale' => $language->code]);
    }
    foreach ($data['slots'] as $slot) {
      foreach ($slot['blocks'] as $block) {
        $this->bind($block, $config['entity']->handle(), $record, $localeLinks, (int) $page->id);
      }
    }
    $title = data_get($record, $config['publication']['title_field']);
    $description = $config['publication']['description_field'] ? strip_tags((string) data_get($record, $config['publication']['description_field'])) : null;
    $url = $this->inspector->url($rule, $inspection['slug'], $locale);
    $data['publicMeta'] = array_replace($data['publicMeta'], ['title' => $title, 'og_title' => $title, 'meta_description' => $description,
      'og_description' => $description, 'canonical_url' => $url, 'og_url' => $url]);
    if ($field = $config['publication']['image_field']) {
      $image = data_get($record, $field);
      $data['publicMeta']['og_image'] = $config['entity']->fieldDefinitions()[$field]['type'] === 'media'
        ? Media::find($image)?->transformUrl('social') : $image;
    }

    return view('webblocks-cms::public.pages.show', [...$data, 'previewMode' => ! $public])->render();
  }

  private function bind(Block $block, string $source, array $record, array $localeLinks, int $pageId): void
  {
    $block->setAttribute('automatic_page_record', ['source' => (int) $block->page_id === $pageId ? $source : null, 'record' => $record, 'locale_links' => $localeLinks]);
    foreach ($block->children as $child) {
      $this->bind($child, $source, $record, $localeLinks, $pageId);
    }
  }

  private function blockRelations(bool $public = false): array
  {
    return ['blockType', 'slotType', 'asset', 'blockAssets.asset', 'textTranslations', 'buttonTranslations', 'imageTranslations', 'contactFormTranslations',
      'children' => fn ($query) => $query->when($public, fn ($q) => $q->where('status', 'published'))->with($this->blockRelations($public))->orderBy('sort_order')->orderBy('id')];
  }
}
