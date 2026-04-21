<?php

namespace Database\Seeders;

use App\Support\System\InstalledVersionStore;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            FoundationSiteLocaleSeeder::class,
            CoreCatalogSeeder::class,
            WebBlocksFoundationSeeder::class,
            WebBlocksLandingContentSeeder::class,
        ]);

        app(InstalledVersionStore::class)->persist((string) config('app.version', 'dev'));
    }
}
