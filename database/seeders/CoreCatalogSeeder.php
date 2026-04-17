<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class CoreCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PageTypeSeeder::class,
            LayoutTypeSeeder::class,
            SlotTypeSeeder::class,
            BlockTypeSeeder::class,
        ]);
    }
}
