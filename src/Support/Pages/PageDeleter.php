<?php

namespace WebBlocks\Cms\Support\Pages;

use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Models\AutomaticPageAdoption;
use WebBlocks\Cms\Models\Page;

class PageDeleter
{
  public function delete(Page $page): void
  {
    if (Schema::hasTable('wbcms_automatic_page_adoptions') && AutomaticPageAdoption::query()->where('page_id', $page->id)->exists()) {
      throw ValidationException::withMessages(['page' => __('webblocks-cms::automatic-pages.retained_page')]);
    }

    $page->delete();
  }
}
