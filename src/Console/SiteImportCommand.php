<?php

namespace WebBlocks\Cms\Console;

use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use WebBlocks\Cms\Models\SiteImport;
use WebBlocks\Cms\Support\Sites\ExportImport\SiteImportManager;
use WebBlocks\Cms\Support\Sites\ExportImport\SiteImportOptions;

class SiteImportCommand extends Command
{
  protected $signature = 'site:import
    {archive? : Absolute or relative path to a site export package zip}
    {--name= : New site name}
    {--handle= : New site handle}
    {--domain= : Optional new site domain}
    {--resume= : Continue an unfinished import by its id instead of starting one}
    {--force : Skip confirmation prompt}';

  protected $description = 'Import a portable site package as a new local site';

  public function __construct(
    private readonly SiteImportManager $siteImportManager,
  ) {
    parent::__construct();
  }

  public function handle(): int
  {
    // An import that was killed mid-run leaves committed work and a cursor.
    // Resuming continues from it; starting over would build a second site.
    if ($resumeId = $this->option('resume')) {
      return $this->resume((int) $resumeId);
    }

    $archivePath = (string) $this->argument('archive');

    if ($archivePath === '') {
      $this->error('Provide an archive path, or --resume={id} to continue an unfinished import.');

      return self::FAILURE;
    }

    if (! is_file($archivePath)) {
      $this->error('Import archive file was not found. Use an existing local zip file path.');

      return self::FAILURE;
    }

    if (! $this->option('force') && ! $this->confirm('Import this package as a new site?')) {
      $this->line('Import cancelled.');

      return self::SUCCESS;
    }

    try {
      $uploadedFile = new UploadedFile($archivePath, basename($archivePath), 'application/zip', null, true);
      $siteImport = $this->siteImportManager->inspectUpload($uploadedFile);
      $manifest = $siteImport->manifest_json ?? [];
      $siteImport = $this->siteImportManager->import($siteImport, SiteImportOptions::fromArray([
        'site_name' => $this->option('name') ?: ($manifest['source_site_name'] ?? 'Imported Site'),
        'site_handle' => $this->option('handle') ?: ($manifest['source_site_handle'] ?? null),
        'site_domain' => $this->option('domain'),
      ]));
    } catch (RuntimeException $exception) {
      $this->error($exception->getMessage());

      return self::FAILURE;
    }

    $this->line('site imported successfully.');
    $this->line('target site: '.($siteImport->targetSite?->name ?? '-').' (#'.($siteImport->targetSite?->id ?? '-').')');
    $this->line('handle: '.($siteImport->targetSite?->handle ?? '-'));
    $this->line('domain: '.($siteImport->targetSite?->domain ?? '-'));

    foreach (($siteImport->summary_json ?? []) as $label => $value) {
      $this->line(str_replace('_', ' ', $label).': '.$value);
    }

    return self::SUCCESS;
  }

  private function resume(int $siteImportId): int
  {
    $siteImport = SiteImport::query()->find($siteImportId);

    if (! $siteImport) {
      $this->error('No import found with id '.$siteImportId.'.');

      return self::FAILURE;
    }

    if ($siteImport->isCompleted()) {
      $this->line('That import already finished.');

      return self::SUCCESS;
    }

    if (! $siteImport->isResumable()) {
      $this->error('That import has not started yet. Run it with the archive path instead.');

      return self::FAILURE;
    }

    $this->line('Resuming from '.$siteImport->resume_phase.' at '.$siteImport->progressPercent().'%.');

    try {
      $siteImport = $this->siteImportManager->import($siteImport, $this->siteImportManager->resumeOptions($siteImport));
    } catch (RuntimeException $exception) {
      $this->error($exception->getMessage());

      return self::FAILURE;
    }

    $this->line('site imported successfully.');
    $this->line('target site: '.($siteImport->targetSite?->name ?? '-').' (#'.($siteImport->targetSite?->id ?? '-').')');

    return self::SUCCESS;
  }
}
