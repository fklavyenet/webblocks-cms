<?php

namespace WebBlocks\Cms\Database\Seeders;

use Illuminate\Database\Seeder;
use WebBlocks\Cms\Support\Catalog\CoreLayoutCatalogSyncer;

class PageLayoutSeeder extends Seeder
{
  public function __construct(
    private readonly CoreLayoutCatalogSyncer $syncer,
  ) {}

  public function run(): void
  {
    $this->syncer->sync();
  }
}
