<?php

namespace App\Support\Locales;

use App\Models\BlockButtonTranslation;
use App\Models\BlockContactFormTranslation;
use App\Models\BlockImageTranslation;
use App\Models\BlockTextTranslation;
use App\Models\Locale;
use App\Models\PageTranslation;
use App\Models\SiteLocale;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class LocaleLifecycleGuard
{
    public function inspect(Locale $locale): LocaleLifecycleReport
    {
        return $this->inspectMany(collect([$locale]))->get($locale->id);
    }

    public function inspectMany(Collection $locales): Collection
    {
        $locales = $locales->filter(fn ($locale) => $locale instanceof Locale)->values();
        $localeIds = $locales->pluck('id')->map(fn ($id) => (int) $id)->filter()->values()->all();

        if ($localeIds === []) {
            return collect();
        }

        $siteCounts = $this->countByLocale(SiteLocale::class, $localeIds);
        $pageCounts = $this->countByLocale(PageTranslation::class, $localeIds);
        $blockTextCounts = $this->countByLocale(BlockTextTranslation::class, $localeIds);
        $blockButtonCounts = $this->countByLocale(BlockButtonTranslation::class, $localeIds);
        $blockImageCounts = $this->countByLocale(BlockImageTranslation::class, $localeIds);
        $blockContactFormCounts = $this->countByLocale(BlockContactFormTranslation::class, $localeIds);

        return $locales->mapWithKeys(function (Locale $locale) use ($siteCounts, $pageCounts, $blockTextCounts, $blockButtonCounts, $blockImageCounts, $blockContactFormCounts) {
            $blockTranslationRows = (int) ($blockTextCounts[$locale->id] ?? 0)
                + (int) ($blockButtonCounts[$locale->id] ?? 0)
                + (int) ($blockImageCounts[$locale->id] ?? 0)
                + (int) ($blockContactFormCounts[$locale->id] ?? 0);

            return [$locale->id => new LocaleLifecycleReport($locale, [
                'site_assignments' => (int) ($siteCounts[$locale->id] ?? 0),
                'page_translations' => (int) ($pageCounts[$locale->id] ?? 0),
                'block_translation_rows' => $blockTranslationRows,
            ])];
        });
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<int, int>
     */
    private function countByLocale(string $modelClass, array $localeIds): array
    {
        return $modelClass::query()
            ->selectRaw('locale_id, count(*) as aggregate')
            ->whereIn('locale_id', $localeIds)
            ->groupBy('locale_id')
            ->pluck('aggregate', 'locale_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }
}
