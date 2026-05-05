<?php

namespace App\Support\Pages;

use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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
            NavigationItem::query()->where('page_id', $lockedPage->id)->lockForUpdate()->get();

            $this->validator->validate($lockedPage, $targetSite);

            $this->revisionManager->capture(
                $lockedPage->fresh(['site', 'translations.locale', 'slots.slotType', 'slots.sharedSlot', 'blocks']),
                $actor,
                'Pre-move safety snapshot',
                'Page state was captured before moving from '.$sourceSite->name.' to '.$targetSite->name.'.',
            );

            $translationPayloads = $lockedPage->translations
                ->map(fn (PageTranslation $translation) => [
                    'locale_id' => $translation->locale_id,
                    'name' => $translation->name,
                    'slug' => $translation->slug,
                    'path' => $translation->path,
                    'created_at' => $translation->created_at,
                    'updated_at' => $translation->updated_at,
                ])
                ->values();

            DB::table('page_translations')
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
                    'created_at' => $payload['created_at'] instanceof Carbon ? $payload['created_at'] : now(),
                    'updated_at' => now(),
                ])
                ->all();

            DB::table('page_translations')->insert($translationRows);

            $lockedPage->slots()->whereIn('id', $validation->sharedSlotRemaps->keys()->all())->get()
                ->each(function ($slot) use ($validation): void {
                    $slot->forceFill([
                        'shared_slot_id' => $validation->sharedSlotRemaps[$slot->id],
                    ])->save();
                });

            NavigationItem::query()
                ->where('page_id', $lockedPage->id)
                ->update(['site_id' => $targetSite->id]);

            DB::table('page_revisions')
                ->where('page_id', $lockedPage->id)
                ->update(['site_id' => $targetSite->id]);

            $this->revisionManager->capture(
                $lockedPage->fresh(['site', 'translations.locale', 'slots.slotType', 'slots.sharedSlot', 'blocks']),
                $actor,
                'Page moved to another site',
                'Page site ownership was moved from '.$sourceSite->name.' to '.$targetSite->name.'.',
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
