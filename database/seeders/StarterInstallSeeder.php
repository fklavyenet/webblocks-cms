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

        throw new \RuntimeException('StarterInstallSeeder is quarantined while the CMS foundation is limited to header and plain_text blocks. Rebuild starter install content deliberately before re-enabling this seeder.');
    }
}
