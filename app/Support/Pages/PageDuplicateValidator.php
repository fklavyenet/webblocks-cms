<?php

namespace App\Support\Pages;

use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\SharedSlot;
use App\Models\Site;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class PageDuplicateValidator
{
    public function validate(Page $page, Site $targetSite, Collection $translations): PageDuplicateValidationResult
    {
        $page->loadMissing([
            'site',
            'translations.locale',
            'slots.slotType',
            'slots.sharedSlot',
            'navigationItems',
        ]);

        $errors = [];
        $sourceTranslations = $page->translations
            ->sortBy(fn (PageTranslation $translation) => [$translation->locale?->is_default ? 0 : 1, $translation->locale_id])
            ->values();
        $sourceLocaleIds = $sourceTranslations->pluck('locale_id')->sort()->values();
        $providedLocaleIds = $translations->pluck('locale_id')->sort()->values();

        if ($sourceLocaleIds->all() !== $providedLocaleIds->all()) {
            $errors['translations'][] = 'Provide title and slug values for each source page translation.';
        }

        $incompatibleLocales = $sourceTranslations
            ->filter(fn (PageTranslation $translation) => ! $targetSite->hasEnabledLocale($translation->locale_id))
            ->map(fn (PageTranslation $translation) => $translation->locale?->code)
            ->filter()
            ->values();

        if ($incompatibleLocales->isNotEmpty()) {
            $errors['target_site_id'][] = 'The target site does not support these page locales: '.$incompatibleLocales->implode(', ').'.';
        }

        foreach ($translations as $translation) {
            $conflict = PageTranslation::query()
                ->where('site_id', $targetSite->id)
                ->where('locale_id', $translation['locale_id'])
                ->where('path', $translation['path'])
                ->exists();

            if (! $conflict) {
                continue;
            }

            $localeCode = $sourceTranslations
                ->firstWhere('locale_id', $translation['locale_id'])
                ?->locale?->code ?? (string) $translation['locale_id'];

            $errors[$translation['slug_field']][] = 'The target site already has a page at ['.$translation['path'].'] for locale ['.$localeCode.'].';
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

            if ((int) $page->site_id === (int) $targetSite->id) {
                $sharedSlotRemaps->put($slot->id, $sharedSlot->id);

                continue;
            }

            $targetSharedSlot = SharedSlot::query()
                ->where('site_id', $targetSite->id)
                ->where('handle', $sharedSlot->handle)
                ->first();

            if (! $targetSharedSlot instanceof SharedSlot) {
                $errors['target_site_id'][] = 'Shared Slot ['.$sharedSlot->handle.'] must exist on the target site before duplicating this page.';

                continue;
            }

            $originalSiteId = $page->site_id;
            $page->site_id = $targetSite->id;
            $compatibilityIssues = $targetSharedSlot->compatibilityIssuesFor($page, $slot->slotSlug());
            $page->site_id = $originalSiteId;

            if ($compatibilityIssues !== []) {
                $errors['target_site_id'][] = 'Shared Slot ['.$sharedSlot->handle.'] is not compatible with target slot ['.$slot->slotSlug().'].'
                    .' Required checks failed: '.implode(', ', $compatibilityIssues).'.';

                continue;
            }

            $sharedSlotRemaps->put($slot->id, $targetSharedSlot->id);
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return new PageDuplicateValidationResult(
            incompatibleLocaleCodes: $incompatibleLocales,
            sharedSlotRemaps: $sharedSlotRemaps,
            sourceNavigationCount: $page->navigationItems->count(),
        );
    }
}
