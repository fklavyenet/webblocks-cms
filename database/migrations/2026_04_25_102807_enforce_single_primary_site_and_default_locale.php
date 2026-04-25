<?php

use App\Models\Locale;
use App\Models\Site;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $primarySiteId = Site::query()
            ->where('is_primary', true)
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->value('id')
            ?? Site::query()->orderBy('id')->value('id');

        if ($primarySiteId) {
            Site::query()->update(['is_primary' => false]);
            Site::query()->whereKey($primarySiteId)->update(['is_primary' => true]);
        }

        $defaultLocaleId = Locale::query()
            ->where('is_default', true)
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->value('id')
            ?? Locale::query()->orderBy('id')->value('id');

        if ($defaultLocaleId) {
            Locale::query()->update(['is_default' => false]);
            Locale::query()->whereKey($defaultLocaleId)->update(['is_default' => true, 'is_enabled' => true]);

            Site::query()->get()->each(function (Site $site) use ($defaultLocaleId): void {
                $site->locales()->syncWithoutDetaching([$defaultLocaleId => ['is_enabled' => true]]);
            });

            DB::table('system_settings')->updateOrInsert(
                ['key' => 'system.default_locale'],
                ['value' => Locale::query()->whereKey($defaultLocaleId)->value('code')],
            );
        }
    }

    public function down(): void
    {
        // Invariant cleanup is intentionally irreversible.
    }
};
