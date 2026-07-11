<?php

namespace WebBlocks\Cms\Support\Pages;

use WebBlocks\Cms\Models\Page;

class PageDeleter
{
  public function delete(Page $page): void
  {
    $page->delete();
  }
}
