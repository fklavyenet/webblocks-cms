<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class StarterInstallSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            CoreCatalogSeeder::class,
            StarterContentSeeder::class,
        ]);
    }
}
