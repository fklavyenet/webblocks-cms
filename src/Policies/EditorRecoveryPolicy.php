<?php

namespace WebBlocks\Cms\Policies;

use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Support\Pages\PageWorkflowManager;

final class EditorRecoveryPolicy
{
  public function edit($user, Page $page): bool
  {
    return $user && $user->is_active && $page->status === Page::STATUS_DRAFT
      && ! $page->isSharedSlotSourcePage() && ! $page->isRevisionRestoreCandidate()
      && app(CmsAuthorization::class)->allows($user, 'cms.pages.read', $page->site_id)
      && app(PageWorkflowManager::class)->canEditContent($user, $page);
  }

  public function authorizeContext($user, Page $page, ?int $blockId, string $locale): ?Block
  {
    abort_unless($this->edit($user, $page), 403);
    $available = $page->availableSiteLocales();
    abort_unless($available->contains('code', $locale), 404);
    if (! $blockId) {
      abort_unless($available->firstWhere('is_default', true)?->code === $locale, 404);

      return null;
    }
    $block = Block::query()->where('page_id', $page->id)->findOrFail($blockId);
    if ($available->firstWhere('is_default', true)?->code !== $locale) {
      abort_unless(app(CmsAuthorization::class)->allows($user, 'cms.translations.update', $page->site_id), 403);
    }

    return $block;
  }
}
