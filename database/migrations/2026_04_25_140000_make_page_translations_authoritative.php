<?php

use App\Models\Page;
use App\Models\PageTranslation;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $defaultLocaleId = Page::defaultLocaleId();

        if (! $defaultLocaleId) {
            return;
        }

        Page::query()
            ->with('translations')
            ->orderBy('id')
            ->get()
            ->each(function (Page $page) use ($defaultLocaleId): void {
                $existingTranslation = $page->translations->firstWhere('locale_id', $defaultLocaleId);

                if ($existingTranslation) {
                    return;
                }

                $rawSlug = (string) $page->getRawOriginal('slug');

                if ($rawSlug === '') {
                    return;
                }

                PageTranslation::query()->create([
                    'page_id' => $page->id,
                    'locale_id' => $defaultLocaleId,
                    'name' => (string) $page->getRawOriginal('title'),
                    'slug' => $rawSlug,
                    'path' => PageTranslation::pathFromSlug($rawSlug),
                    'created_at' => $page->created_at,
                    'updated_at' => $page->updated_at,
                ]);
            });
    }

    public function down(): void
    {
        // Phase 4 keeps compatibility columns in place but stops using them as the source of truth.
    }
};
