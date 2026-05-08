<?php

namespace App\Support\Icons;

use App\Models\IconCatalogItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class IconCatalog
{
    public function navigationPickerOptions(?string $selectedSlug = null, ?string $currentSlug = null): Collection
    {
        $selectedSlug = $this->normalizeSlug($selectedSlug);
        $currentSlug = $this->normalizeSlug($currentSlug);

        $options = $this->navigationIconsQuery()
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->unique('slug')
            ->values()
            ->map(fn (IconCatalogItem $icon) => [
                'slug' => $icon->slug,
                'label' => $icon->label,
            ]);

        if ($selectedSlug !== null && ! $options->contains(fn (array $option) => $option['slug'] === $selectedSlug)) {
            $options->prepend($this->syntheticOption($selectedSlug, $currentSlug));
        }

        return $options->values();
    }

    public function isValidNavigationSelection(?string $slug, ?string $currentSlug = null): bool
    {
        $slug = $this->normalizeSlug($slug);
        $currentSlug = $this->normalizeSlug($currentSlug);

        if ($slug === null) {
            return true;
        }

        if ($currentSlug !== null && $slug === $currentSlug) {
            return true;
        }

        return $this->navigationIconsQuery()->where('slug', $slug)->exists();
    }

    public function normalizeSlug(?string $slug): ?string
    {
        return IconCatalogItem::normalizeSlug($slug);
    }

    private function navigationIconsQuery(): Builder
    {
        return IconCatalogItem::query()
            ->active()
            ->tagged('navigation');
    }

    private function syntheticOption(string $slug, ?string $currentSlug): array
    {
        $icon = IconCatalogItem::query()
            ->where('slug', $slug)
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->first();

        if ($icon) {
            $status = [];

            if (! $icon->is_active) {
                $status[] = 'inactive';
            }

            if (! $icon->isTagged('navigation')) {
                $status[] = 'not for navigation';
            }

            $prefix = $currentSlug !== null && $slug === $currentSlug ? 'Current' : 'Selected';

            return [
                'slug' => $slug,
                'label' => $prefix.': '.$icon->label.' ('.implode(', ', $status === [] ? ['unavailable'] : $status).')',
            ];
        }

        $prefix = $currentSlug !== null && $slug === $currentSlug ? 'Current' : 'Selected';

        return [
            'slug' => $slug,
            'label' => $prefix.': '.Str::of($slug)->replace('-', ' ')->title()->toString().' (unlisted)',
        ];
    }
}
