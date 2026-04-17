<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ShowcaseInstallSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CoreCatalogSeeder::class,
            FullShowcaseSeeder::class,
        ]);
    }
}
