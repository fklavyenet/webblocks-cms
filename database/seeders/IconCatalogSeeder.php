<?php

namespace WebBlocks\Cms\Database\Seeders;

use Illuminate\Database\Seeder;
use WebBlocks\Cms\Support\Icons\WebBlocksIconManifestSyncer;

class IconCatalogSeeder extends Seeder
{
  public function __construct(
    private readonly WebBlocksIconManifestSyncer $syncer,
  ) {}

  /**
   * Seeds the whole shipped icon catalog, not a hand-written subset of it.
   *
   * This used to write 20 navigation slugs, which left every content icon
   * field in the admin offering nothing until an operator found the manual
   * manifest sync. The package carries the manifest for its pinned UI version,
   * so a fresh install gets all of it with no network at all.
   */
  public function run(): void
  {
    $this->syncer->sync(WebBlocksIconManifestSyncer::installManifestSource());
  }
}
