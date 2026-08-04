<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Install\DefaultHomepageProvisioner;
use WebBlocks\Cms\Support\Install\StarterContentInstaller;

/**
 * Operator entry point for filling an empty home page with starter content.
 *
 * The seeder behind it is reachable as `db:seed --class=...`, but that spells a
 * namespaced class name into whatever runs it — and a hosting panel's Artisan
 * box ate the backslashes, turning the class into one unresolvable word. A
 * command with no arguments to mangle is the shape this procedure needs.
 *
 * It also deliberately skips Laravel's production confirmation. That prompt
 * cannot be answered by the non-interactive runners this exists for, and the
 * guarantee it would be protecting is already structural: the installer writes
 * only into a page that has no blocks at all.
 */
class StarterContentCommand extends Command
{
  protected $signature = 'webblocks:starter-content
    {--site= : Handle of the site to fill; defaults to the primary site}';

  protected $description = 'Fill a site\'s empty home page with the shipped starter content';

  public function __construct(
    private readonly DefaultHomepageProvisioner $defaultHomepageProvisioner,
    private readonly StarterContentInstaller $starterContentInstaller,
  ) {
    parent::__construct();
  }

  public function handle(): int
  {
    $site = $this->resolveSite();

    if (! $site) {
      $this->components->error($this->option('site')
        ? 'No site found with handle ['.$this->option('site').'].'
        : 'No site exists yet. Run webblocks:install first.');

      return self::FAILURE;
    }

    $homePage = $this->defaultHomepageProvisioner->provision($site);
    $result = $this->starterContentInstaller->install($homePage);

    if (! $result->installed) {
      $this->components->warn('Nothing was written to '.$site->handle.': '.$result->reason);

      return self::SUCCESS;
    }

    $this->components->info('Installed '.$result->blocksCreated.' starter content blocks on the home page of '.$site->handle.'.');

    if ($result->skippedBlockTypes !== []) {
      $this->components->warn('Skipped starter blocks with unavailable block types: '.implode(', ', $result->skippedBlockTypes).'.');
    }

    $this->components->info('Open / to see it, or edit it under Pages in the admin.');

    return self::SUCCESS;
  }

  private function resolveSite(): ?Site
  {
    $handle = trim((string) $this->option('site'));

    if ($handle !== '') {
      return Site::query()->where('handle', $handle)->first();
    }

    return Site::query()->where('is_primary', true)->orderBy('id')->first()
      ?? Site::query()->orderBy('id')->first();
  }
}
