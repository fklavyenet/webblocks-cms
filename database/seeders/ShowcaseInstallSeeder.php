<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\GuardsInitializedSites;
use Illuminate\Database\Seeder;

class ShowcaseInstallSeeder extends Seeder
{
    use GuardsInitializedSites;

    public function run(): void
    {
        $this->ensureSiteIsNotInitialized(self::class);

        $this->call([
            CoreCatalogSeeder::class,
            FullShowcaseSeeder::class,
        ]);
    }
}
