<?php

namespace WebBlocks\Cms\Database\Seeders;

use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\PageLayoutSeeder;
use Illuminate\Database\Seeder;

class CoreCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            IconCatalogSeeder::class,
            PageTypeSeeder::class,
            LayoutTypeSeeder::class,
            PageLayoutSeeder::class,
            SlotTypeSeeder::class,
            BlockTypeSeeder::class,
        ]);
    }
}
