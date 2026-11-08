<?php

namespace WebBlocks\Cms\Support\Pages;

use App\Models\User;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockMedia;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Models\PageRevisionCandidate;
use WebBlocks\Cms\Support\Contact\ContactConsentRequirements;
use WebBlocks\Cms\Support\Revisions\RevisionContentUpdates;
use WebBlocks\Cms\Support\Revisions\SnapshotContent;
use WebBlocks\Cms\Support\Search\PublicSearchIndexer;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSourcePageManager;
use WebBlocks\Cms\Support\Sitemap\SitemapCache;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;

final class PageLocaleRestoreManager
{
  public function __construct(private readonly PageLocaleRestorePlanner $planner, private readonly PageRevisionManager $revisions) {}

  public function lock(Page $page): void
  {
    $page->site()->lockForUpdate()->firstOrFail();
    $page->site->locales()->orderBy('wbcms_locales.id')->lockForUpdate()->get();
    Locale::query()->orderBy('id')->lockForUpdate()->get();
    $pages = [$page];
    foreach ($page->slots()->with('sharedSlot')->lockForUpdate()->get() as $slot) {
      if ($slot->sharedSlot) {
        $slot->sharedSlot()->lockForUpdate()->get();
        $source = app(SharedSlotSourcePageManager::class)->findFor($slot->sharedSlot);
        if ($source) {
          $pages[] = $source;
        }
      }
    }
    foreach (collect($pages)->unique('id')->sortBy('id') as $ownedPage) {
      Page::whereKey($ownedPage->id)->lockForUpdate()->firstOrFail();
      $ownedPage->translations()->orderBy('id')->lockForUpdate()->get();
      $ownedPage->pageAssets()->orderBy('id')->lockForUpdate()->get();
      foreach ($ownedPage->blocks()->orderBy('id')->lockForUpdate()->get() as $block) {
        foreach (['textTranslations', 'buttonTranslations', 'imageTranslations', 'contactFormTranslations', 'pluginTranslations'] as $relation) {
          $block->{$relation}()->orderBy('id')->lockForUpdate()->get();
        }
        foreach ($block->blockAssets()->orderBy('id')->lockForUpdate()->get() as $media) {
          $media->galleryItemTranslations()->orderBy('id')->lockForUpdate()->get();
        }
      }
    }
  }

  public function apply(PageRevisionCandidate $candidate, Page $page, PageRevision $revision, User $actor, bool $confirmed): Page
  {
    return app(RevisionContentUpdates::class)->during(fn () => $this->applyLocked($candidate, $page, $revision, $actor, $confirmed), 3);
  }

  private function applyLocked(PageRevisionCandidate $candidate, Page $page, PageRevision $revision, User $actor, bool $confirmed): Page
  {
    abort_unless((int) $candidate->page_id === (int) $page->id && (int) $candidate->page_revision_id === (int) $revision->id && $candidate->status === PageRevisionCandidate::STATUS_READY, 409);
    if (! $confirmed) {
      throw new PageLocaleRestoreException([['code' => 'confirmation_required']]);
    }
    $this->lock($page);
    $plan = $this->planner->plan($page->fresh(), $revision->fresh(), $candidate->restore_scope, $candidate->restore_locale_id);
    if (! $plan['ok']) {
      throw new PageLocaleRestoreException($plan['issues']);
    }
    if (! hash_equals((string) $candidate->source_fingerprint, $plan['source_fingerprint']) || ! hash_equals((string) $candidate->revision_fingerprint, $plan['revision_fingerprint'])) {
      throw new PageLocaleRestoreException([['code' => 'stale']]);
    }
    $preview = $candidate->candidatePage;
    if (! $preview) {
      throw new PageLocaleRestoreException([['code' => 'preview_changed']]);
    }
    $this->lock($preview);
    if (! hash_equals((string) $candidate->preview_fingerprint, SnapshotContent::hash($this->revisions->snapshotForInspection($preview->fresh())))) {
      throw new PageLocaleRestoreException([['code' => 'preview_changed']]);
    }
    $adminLocale = app(AdminLocaleResolver::class)->locale();
    $text = fn ($key, $replace = []) => app(CmsTranslator::class)->admin('page_revisions.locale_restore.'.$key, $adminLocale, $replace);
    $this->revisions->capture($page->fresh(), $actor, 'Pre-restore safety snapshot', $text('safety_reason', ['language' => $plan['locale_code']]), event: 'revision_restored', source: 'restore', force: true);
    foreach ($plan['changes'] as $change) {
      if ($change['entity'] === 'page') {
        $page->translations()->updateOrCreate(['locale_id' => $plan['locale_id']], $change['after']);
      } else {
        $owner = $change['entity'] === 'gallery' ? BlockMedia::findOrFail($change['owner_id']) : Block::findOrFail($change['owner_id']);
        $owner->{$change['relation']}()->updateOrCreate(['locale_id' => $plan['locale_id']], $change['after']);
      }
    }
    // Page's general saved hook synchronizes every locale and touches their timestamps.
    // Scoped restore saves only the parent audit fields, then refreshes derived state explicitly.
    $page->forceFill(['updated_by_user_id' => $actor->id])->saveQuietly();
    if ($page->status === Page::STATUS_PUBLISHED) {
      app(ContactConsentRequirements::class)->assertPage($page->fresh());
    }
    app(PublicSearchIndexer::class)->refreshPage($page->fresh());
    app(SitemapCache::class)->invalidateSite((int) $page->site_id);
    $this->revisions->capture($page->fresh(), $actor, 'Language restored', $text('audit_reason', ['language' => $plan['locale_code'], 'revision' => $revision->id, 'scope' => $text('scopes.'.$candidate->restore_scope)]), $revision, event: 'revision_restored', source: 'restore', force: true);

    return $page->fresh();
  }
}
