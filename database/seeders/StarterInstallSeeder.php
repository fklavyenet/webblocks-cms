<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\GuardsInitializedSites;
use Illuminate\Database\Seeder;

class StarterInstallSeeder extends Seeder
{
    use GuardsInitializedSites;

    public function run(): void
    {
        $this->ensureSiteIsNotInitialized(self::class);

        $this->call([
            CoreCatalogSeeder::class,
            StarterContentSeeder::class,
        ]);
    }
}
