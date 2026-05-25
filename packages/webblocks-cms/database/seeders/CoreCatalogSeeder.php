<?php

namespace WebBlocks\Cms\Database\Seeders;

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
