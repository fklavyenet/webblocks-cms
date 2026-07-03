<?php

namespace WebBlocks\Cms\Support\Pages;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;

class PageSiteMover
{
  public function __construct(
    private readonly PageRevisionManager $revisionManager,
    private readonly PageSiteMoveValidator $validator,
  ) {}

  public function move(Page $page, Site $targetSite, User $actor): PageSiteMoveResult
  {
    $page->loadMissing([
      'site',
      'translations.locale',
      'slots.sharedSlot',
      'slots.slotType',
      'navigationItems',
    ]);

    $sourceSite = $page->site;
    $validation = $this->validator->validate($page, $targetSite);

    return DB::transaction(function () use ($page, $targetSite, $actor, $sourceSite, $validation): PageSiteMoveResult {
      $lockedPage = Page::query()
        ->with(['translations.locale', 'slots.sharedSlot', 'slots.slotType', 'navigationItems'])
        ->lockForUpdate()
        ->findOrFail($page->id);

      $lockedPage->translations()->lockForUpdate()->get();
      $lockedPage->slots()->lockForUpdate()->get();
      $lockedPage->pageAssets()->lockForUpdate()->get();
      NavigationItem::query()->where('page_id', $lockedPage->id)->lockForUpdate()->get();

      $this->validator->validate($lockedPage, $targetSite);

      $this->revisionManager->capture(
        $lockedPage->fresh(['site', 'translations.locale', 'slots.slotType', 'slots.sharedSlot', 'blocks']),
        $actor,
        'Pre-move safety snapshot',
        'Page state was captured before moving from '.$sourceSite->name.' to '.$targetSite->name.'.',
        event: 'workflow_changed',
      );

      $translationPayloads = $lockedPage->translations
        ->map(fn (PageTranslation $translation) => [
          'locale_id' => $translation->locale_id,
          'name' => $translation->name,
          'slug' => $translation->slug,
          'path' => $translation->path,
          'seo_title' => $translation->seo_title,
          'seo_description' => $translation->seo_description,
          'seo_keywords' => $translation->seo_keywords,
          'og_title' => $translation->og_title,
          'og_description' => $translation->og_description,
          'og_image_media_id' => $translation->og_image_media_id,
          'created_at' => $translation->created_at,
          'updated_at' => $translation->updated_at,
        ])
        ->values();

      DB::table('wbcms_page_translations')
        ->where('page_id', $lockedPage->id)
        ->where('site_id', $sourceSite->id)
        ->delete();

      $lockedPage->forceFill(['site_id' => $targetSite->id]);
      $lockedPage->saveQuietly();

      $translationRows = $translationPayloads
        ->map(fn (array $payload) => [
          'page_id' => $lockedPage->id,
          'site_id' => $targetSite->id,
          'locale_id' => $payload['locale_id'],
          'name' => $payload['name'],
          'slug' => $payload['slug'],
          'path' => $payload['path'],
          'seo_title' => $payload['seo_title'],
          'seo_description' => $payload['seo_description'],
          'seo_keywords' => $payload['seo_keywords'],
          'og_title' => $payload['og_title'],
          'og_description' => $payload['og_description'],
          'og_image_media_id' => $payload['og_image_media_id'],
          'created_at' => $payload['created_at'] instanceof Carbon ? $payload['created_at'] : now(),
          'updated_at' => now(),
        ])
        ->all();

      DB::table('wbcms_page_translations')->insert($translationRows);

      $lockedPage->slots()->whereIn('id', $validation->sharedSlotRemaps->keys()->all())->get()
        ->each(function ($slot) use ($validation): void {
          $slot->forceFill([
            'shared_slot_id' => $validation->sharedSlotRemaps[$slot->id],
          ])->save();
        });

      NavigationItem::query()
        ->where('page_id', $lockedPage->id)
        ->update(['site_id' => $targetSite->id]);

      DB::table('wbcms_page_revisions')
        ->where('page_id', $lockedPage->id)
        ->update(['site_id' => $targetSite->id]);

      $this->revisionManager->capture(
        $lockedPage->fresh(['site', 'translations.locale', 'slots.slotType', 'slots.sharedSlot', 'blocks']),
        $actor,
        'Page moved to another site',
        'Page site ownership was moved from '.$sourceSite->name.' to '.$targetSite->name.'.',
        event: 'workflow_changed',
      );

      $movedPage = $lockedPage->fresh(['site', 'translations.locale', 'slots.sharedSlot', 'slots.slotType']);

      return new PageSiteMoveResult(
        page: $movedPage,
        sourceSite: $sourceSite,
        targetSite: $targetSite,
        remappedSharedSlotCount: $validation->sharedSlotRemaps->count(),
        navigationReferenceCount: $validation->navigationReferenceCount,
      );
    });
  }
}
