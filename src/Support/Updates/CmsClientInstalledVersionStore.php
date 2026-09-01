<?php

namespace WebBlocks\Cms\Support\Updates;

use WebBlocks\Cms\Support\System\InstalledVersionStore;
use WebBlocks\Cms\Support\Updates\Client\Contracts\InstalledVersionStore as ClientInstalledVersionStore;

final class CmsClientInstalledVersionStore implements ClientInstalledVersionStore
{
  public function __construct(private readonly InstalledVersionStore $store) {}

  public function persist(string $version): void
  {
    $this->store->persist($version);
  }
}
