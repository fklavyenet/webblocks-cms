<?php

namespace Database\Seeders;

use App\Models\Locale;
use App\Models\Site;
use Illuminate\Database\Seeder;

class FoundationSiteLocaleSeeder extends Seeder
{
    public function run(): void
    {
        $site = Site::query()->updateOrCreate(
            ['handle' => 'default'],
            [
                'name' => 'Default Site',
                'domain' => null,
                'is_primary' => true,
            ],
        );

        $locale = Locale::query()->updateOrCreate(
            ['code' => 'en'],
            [
                'name' => 'English',
                'is_default' => true,
                'is_enabled' => true,
            ],
        );

        $site->locales()->syncWithoutDetaching([
            $locale->id => ['is_enabled' => true],
        ]);
    }
}
