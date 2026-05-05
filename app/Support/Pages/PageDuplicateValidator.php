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
    public function inspect(Page $page, Site $targetSite): PageDuplicateValidationResult
    {
        return $this->analyze($page, $targetSite);
    }

    public function validate(
        Page $page,
        Site $targetSite,
        Collection $translations,
        bool $disableIncompatibleSharedSlots = false,
    ): PageDuplicateValidationResult
    {
        $result = $this->analyze($page, $targetSite, $translations, $disableIncompatibleSharedSlots);

        if ($result->errors !== []) {
            throw ValidationException::withMessages($result->errors);
        }

        return $result;
    }

    private function analyze(
        Page $page,
        Site $targetSite,
        ?Collection $translations = null,
        bool $disableIncompatibleSharedSlots = false,
    ): PageDuplicateValidationResult
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

        if ($translations instanceof Collection) {
            $providedLocaleIds = $translations->pluck('locale_id')->sort()->values();

            if ($sourceLocaleIds->all() !== $providedLocaleIds->all()) {
                $errors['translations'][] = 'Provide title and slug values for each source page translation.';
            }
        }

        $incompatibleLocales = $sourceTranslations
            ->filter(fn (PageTranslation $translation) => ! $targetSite->hasEnabledLocale($translation->locale_id))
            ->map(fn (PageTranslation $translation) => $translation->locale?->code)
            ->filter()
            ->values();

        if ($incompatibleLocales->isNotEmpty()) {
            $errors['target_site_id'][] = 'The target site does not support these page locales: '.$incompatibleLocales->implode(', ').'.';
        }

        foreach ($translations ?? collect() as $translation) {
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
        $sharedSlotCompatibility = collect();
        $disableEligibleSharedSlotIds = collect();

        foreach ($page->slots as $slot) {
            if ($slot->runtimeSourceType() !== $slot::SOURCE_TYPE_SHARED_SLOT) {
                continue;
            }

            $sharedSlot = $slot->sharedSlot;
            $detail = [
                'slot_id' => $slot->id,
                'slot_name' => $slot->slotSlug(),
                'shared_slot_handle' => $sharedSlot?->handle,
                'shared_slot_name' => $sharedSlot?->name,
                'source_site_id' => $page->site_id,
                'target_site_id' => $targetSite->id,
                'target_shared_slot_id' => null,
                'status' => 'unknown',
                'issues' => [],
                'can_disable' => false,
                'message' => null,
            ];

            if (! $sharedSlot instanceof SharedSlot) {
                $detail['status'] = 'missing_source';
                $detail['message'] = 'This page references a missing Shared Slot on slot ['.$slot->slotSlug().'].';
                $sharedSlotCompatibility->push($detail);
                $errors['target_site_id'][] = $detail['message'];

                continue;
            }

            if ((int) $page->site_id === (int) $targetSite->id) {
                $sharedSlotRemaps->put($slot->id, $sharedSlot->id);
                $detail['status'] = 'same_site';
                $detail['target_shared_slot_id'] = $sharedSlot->id;
                $detail['message'] = 'Same-site duplicate will keep Shared Slot ['.$sharedSlot->handle.'] on slot ['.$slot->slotSlug().'].';
                $sharedSlotCompatibility->push($detail);

                continue;
            }

            $targetSharedSlot = SharedSlot::query()
                ->where('site_id', $targetSite->id)
                ->where('handle', $sharedSlot->handle)
                ->first();

            if (! $targetSharedSlot instanceof SharedSlot) {
                $detail['status'] = 'missing_target';
                $detail['can_disable'] = true;
                $detail['message'] = 'Shared Slot ['.$sharedSlot->handle.'] must exist on the target site before duplicating this page.';
                $sharedSlotCompatibility->push($detail);
                $disableEligibleSharedSlotIds->push($slot->id);

                if (! $disableIncompatibleSharedSlots) {
                    $errors['target_site_id'][] = $detail['message'];
                }

                continue;
            }

            $originalSiteId = $page->site_id;
            $page->site_id = $targetSite->id;
            $compatibilityIssues = $targetSharedSlot->compatibilityIssuesFor($page, $slot->slotSlug());
            $page->site_id = $originalSiteId;

            if ($compatibilityIssues !== []) {
                $detail['status'] = 'incompatible_target';
                $detail['target_shared_slot_id'] = $targetSharedSlot->id;
                $detail['issues'] = $compatibilityIssues;
                $detail['can_disable'] = true;
                $detail['message'] = 'Shared Slot ['.$sharedSlot->handle.'] is not compatible with target slot ['.$slot->slotSlug().'].'
                    .' Required checks failed: '.implode(', ', $compatibilityIssues).'.';
                $sharedSlotCompatibility->push($detail);
                $disableEligibleSharedSlotIds->push($slot->id);

                if (! $disableIncompatibleSharedSlots) {
                    $errors['target_site_id'][] = $detail['message'];
                }

                continue;
            }

            $sharedSlotRemaps->put($slot->id, $targetSharedSlot->id);
            $detail['status'] = 'compatible_target';
            $detail['target_shared_slot_id'] = $targetSharedSlot->id;
            $detail['message'] = 'Cross-site duplicate will remap Shared Slot ['.$sharedSlot->handle.'] onto the target site slot ['.$slot->slotSlug().'].';
            $sharedSlotCompatibility->push($detail);
        }

        return new PageDuplicateValidationResult(
            incompatibleLocaleCodes: $incompatibleLocales,
            sharedSlotRemaps: $sharedSlotRemaps,
            sharedSlotCompatibility: $sharedSlotCompatibility,
            disableEligibleSharedSlotIds: $disableEligibleSharedSlotIds->unique()->values(),
            sourceNavigationCount: $page->navigationItems->count(),
            errors: $errors,
        );
    }
}
