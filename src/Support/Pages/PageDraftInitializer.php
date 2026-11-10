<?php

namespace WebBlocks\Cms\Support\Pages;

use RuntimeException;
use WebBlocks\Cms\Models\Page;

final class PageDraftInitializer
{
  public function __construct(private readonly PageLayoutSlotSyncer $slots) {}

  /** Initialize the page within the caller's draft creation transaction. */
  public function create(array $attributes, array $translation, string $layout): Page
  {
    $page = Page::query()->create([
      'page_type' => Page::TYPE_DEFAULT,
      'status' => Page::STATUS_DRAFT,
    ] + $attributes);
    if (! $page->exists) {
      throw new RuntimeException('A page model event cancelled draft creation.');
    }

    $firstTranslation = $page->translations()->create(['site_id' => $page->site_id] + $translation);
    if (! $firstTranslation->exists) {
      throw new RuntimeException('A translation model event cancelled draft creation.');
    }

    $this->slots->seedInitialSlots($page, $layout, requirePersistence: true);

    return $page;
  }
}
