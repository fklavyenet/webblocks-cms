<?php

namespace WebBlocks\Cms\Policies;

use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Access\CmsAuthorization;
use WebBlocks\Cms\Support\EditorRecovery\RecoveryFormContext;
use WebBlocks\Cms\Support\Pages\PageWorkflowManager;
use WebBlocks\Cms\Support\Plugins\PluginBlockCatalog;

final class EditorFormRecoveryPolicy
{
  public function context($user, array $data): RecoveryFormContext
  {
    $acl = app(CmsAuthorization::class);
    abort_unless($user && $user->is_active, 403);
    $kind = $data['form_kind'] ?? null;
    abort_unless(in_array($kind, RecoveryFormContext::KINDS, true), 422);
    $page = $kind === 'new-page' ? null : Page::query()->findOrFail($data['page_id'] ?? 0);
    $site = $page?->site ?? Site::query()->findOrFail($data['site_id'] ?? 0);
    if ($page) {
      abort_unless(! $page->isSharedSlotSourcePage() && ! $page->isRevisionRestoreCandidate()
        && $acl->allows($user, 'cms.pages.read', $site->id)
        && app(PageWorkflowManager::class)->canEditContent($user, $page), 403);
    } else {
      abort_unless($acl->allows($user, 'cms.pages.create', $site->id), 403);
    }
    $locale = $site->enabledLocales()->where('code', $data['locale'] ?? '')->firstOrFail();
    if ($kind === 'new-page') {
      abort_unless($locale->is_default, 404);
    }
    if (in_array($kind, ['new-translation', 'translation'], true) || ! $locale->is_default) {
      abort_unless($acl->allows($user, 'cms.translations.update', $site->id), 403);
    }
    $type = $slot = $parent = $translation = null;
    if ($kind === 'new-block') {
      $type = BlockType::query()->where('status', 'published')->findOrFail($data['block_type_id'] ?? 0);
      abort_unless(app(PluginBlockCatalog::class)->filterDiscoverableBlockTypes(collect([$type]))->isNotEmpty(), 403);
      $slot = SlotType::query()->where('status', 'published')->findOrFail($data['slot_type_id'] ?? 0);
      abort_unless(PageSlot::query()->where('page_id', $page->id)->where('slot_type_id', $slot->id)->where('source_type', 'page')->exists(), 404);
      if (! empty($data['parent_id'])) {
        $parent = Block::query()->where('page_id', $page->id)->where('slot_type_id', $slot->id)->findOrFail($data['parent_id']);
      }
    }
    if ($kind === 'translation') {
      $translation = PageTranslation::query()->where('page_id', $page->id)->where('locale_id', $locale->id)->findOrFail($data['translation_id'] ?? 0);
    }

    return new RecoveryFormContext($kind, $site, $locale, $page, $type, $slot, $parent, $translation);
  }
}
