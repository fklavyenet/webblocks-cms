<?php

namespace WebBlocks\Cms\Database\Seeders;

use Illuminate\Database\Seeder;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Install\DefaultHomepageProvisioner;
use WebBlocks\Cms\Support\Install\StarterContentInstaller;

/**
 * Gives the manual `php artisan db:seed` install the same home page the
 * `webblocks:install` command produces.
 *
 * The page itself is provisioned unconditionally, because a site without a
 * published home page falls through to the host application's Laravel welcome
 * view. Only the starter blocks inside it honour the starter-content setting.
 */
class StarterContentSeeder extends Seeder
{
  public function __construct(
    private readonly DefaultHomepageProvisioner $homepageProvisioner,
    private readonly StarterContentInstaller $starterContentInstaller,
  ) {}

  public function run(): void
  {
    $site = Site::query()->where('is_primary', true)->orderBy('id')->first()
      ?? Site::query()->orderBy('id')->first();

    if (! $site) {
      return;
    }

    $this->starterContentInstaller->install($this->homepageProvisioner->provision($site));
  }
}
