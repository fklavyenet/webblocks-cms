<?php

namespace WebBlocks\Cms\Support\Pages;

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
    $page->translations()->create(['site_id' => $page->site_id] + $translation);
    $this->slots->seedInitialSlots($page, $layout);

    return $page;
  }
}
