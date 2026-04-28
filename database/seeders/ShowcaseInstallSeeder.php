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

        throw new \RuntimeException('ShowcaseInstallSeeder is quarantined while the CMS foundation is limited to header and plain_text blocks. Rebuild showcase install content deliberately before re-enabling this seeder.');
    }
}
