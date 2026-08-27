<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Support\Pages\PageDeleter;

class PrunePromotedStagedUpdatesCommand extends Command
{
  protected $signature = 'webblocks:staged-updates:prune {--dry-run : List eligible technical pages without deleting them}';

  protected $description = 'Delete legacy promoted staged-update pages that were retained as archived pages';

  public function handle(PageDeleter $pageDeleter): int
  {
    $query = Page::query()
      ->where('status', Page::STATUS_ARCHIVED)
      ->where('settings->staged_update->type', 'published_page_update')
      ->where('settings->staged_update->state', 'promoted')
      ->orderBy('id');

    $count = (clone $query)->count();

    if ($this->option('dry-run')) {
      $this->line('eligible='.$count.' deleted=0');

      return self::SUCCESS;
    }

    $deleted = 0;
    $query->chunkById(100, function ($pages) use ($pageDeleter, &$deleted): void {
      foreach ($pages as $page) {
        $pageDeleter->delete($page);
        $deleted++;
      }
    });

    $this->line('eligible='.$count.' deleted='.$deleted);

    return self::SUCCESS;
  }
}
