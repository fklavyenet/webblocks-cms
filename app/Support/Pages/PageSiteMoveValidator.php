<?php

namespace App\Support\Pages;

use App\Models\Page;
use App\Models\SharedSlot;
use App\Models\Site;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PageSiteMoveValidator
{
    public function validate(Page $page, Site $targetSite): PageSiteMoveValidationResult
    {
        $page->loadMissing([
            'site',
            'translations.locale',
            'slots.slotType',
            'slots.sharedSlot',
            'navigationItems',
        ]);

        $errors = [];

        if ((int) $page->site_id === (int) $targetSite->id) {
            $errors['target_site_id'][] = 'Choose a different target site.';
        }

        $incompatibleLocales = $page->translations
            ->filter(fn ($translation) => ! $targetSite->hasEnabledLocale($translation->locale_id))
            ->map(fn ($translation) => $translation->locale?->code)
            ->filter()
            ->values();

        if ($incompatibleLocales->isNotEmpty()) {
            $errors['target_site_id'][] = 'The target site does not support these page locales: '.$incompatibleLocales->implode(', ').'.';
        }

        foreach ($page->translations as $translation) {
            $conflict = $page->translations()
                ->getModel()
                ->newQuery()
                ->where('site_id', $targetSite->id)
                ->where('locale_id', $translation->locale_id)
                ->where('path', $translation->path)
                ->where('page_id', '!=', $page->id)
                ->exists();

            if ($conflict) {
                $localeCode = $translation->locale?->code ?? (string) $translation->locale_id;
                $errors['target_site_id'][] = 'The target site already has a page at ['.$translation->path.'] for locale ['.$localeCode.'].';
            }
        }

        $sharedSlotRemaps = collect();

        foreach ($page->slots as $slot) {
            if ($slot->runtimeSourceType() !== $slot::SOURCE_TYPE_SHARED_SLOT) {
                continue;
            }

            $sharedSlot = $slot->sharedSlot;

            if (! $sharedSlot instanceof SharedSlot) {
                $errors['target_site_id'][] = 'This page references a missing Shared Slot on slot ['.$slot->slotSlug().'].';

                continue;
            }

            $targetSharedSlot = SharedSlot::query()
                ->where('site_id', $targetSite->id)
                ->where('handle', $sharedSlot->handle)
                ->first();

            if (! $targetSharedSlot instanceof SharedSlot) {
                $errors['target_site_id'][] = 'Shared Slot ['.$sharedSlot->handle.'] must exist on the target site before moving this page.';

                continue;
            }

            $originalSiteId = $page->site_id;
            $page->site_id = $targetSite->id;
            $compatibilityIssues = $targetSharedSlot->compatibilityIssuesFor($page, $slot->slotSlug());
            $page->site_id = $originalSiteId;

            if ($compatibilityIssues !== []) {
                $errors['target_site_id'][] = 'Shared Slot ['.$sharedSlot->handle.'] is not compatible with target slot ['.$slot->slotSlug().'].'.
                    ' Required checks failed: '.implode(', ', $compatibilityIssues).'.';

                continue;
            }

            $sharedSlotRemaps->put($slot->id, $targetSharedSlot->id);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return new PageSiteMoveValidationResult(
            incompatibleLocaleCodes: $incompatibleLocales,
            sharedSlotRemaps: $sharedSlotRemaps,
            navigationReferenceCount: $page->navigationItems->count(),
        );
    }
}
