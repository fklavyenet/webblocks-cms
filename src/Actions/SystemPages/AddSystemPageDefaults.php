<?php

namespace WebBlocks\Cms\Actions\SystemPages;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\SystemPages\SystemPageBlocks;
use WebBlocks\Cms\Support\SystemPages\SystemPageRegistry;
use WebBlocks\Cms\Support\SystemPages\SystemPageRenderer;

final class AddSystemPageDefaults
{
  public function execute(Page $page, User $user): void
  {
    DB::transaction(function () use ($page, $user): void {
      $page = Page::query()->whereKey($page->id)->lockForUpdate()->firstOrFail();
      $definition = app(SystemPageRegistry::class)->find(app(SystemPageRenderer::class)->identity($page) ?? '');
      $blocks = app(SystemPageBlocks::class);
      if (! $definition || ! $blocks->canAdd($page)) {
        throw ValidationException::withMessages(['system_page' => __('webblocks-cms::system_pages.defaults_unavailable')]);
      }
      $revisions = app(PageRevisionManager::class);
      $revisions->capture($page->fresh(), $user, 'Before default content', 'Before adding System Page default blocks.', event: 'page_updated');
      $blocks->add($page, $definition);
      $page->update(['updated_by_user_id' => $user->id]);
      $revisions->capture($page->fresh(), $user, 'Default content added', 'System Page default blocks added as drafts.', event: 'page_updated');
    });
  }
}
