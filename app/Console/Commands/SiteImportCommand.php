<?php

namespace App\Console\Commands;

use App\Models\SiteImport;
use App\Support\Sites\ExportImport\SiteImportManager;
use App\Support\Sites\ExportImport\SiteImportOptions;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class SiteImportCommand extends Command
{
    protected $signature = 'site:import
        {archive : Absolute or relative path to a site export package zip}
        {--name= : New site name}
        {--handle= : New site handle}
        {--domain= : Optional new site domain}
        {--force : Skip confirmation prompt}';

    protected $description = 'Import a portable site package as a new local site';

    public function __construct(
        private readonly SiteImportManager $siteImportManager,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $archivePath = (string) $this->argument('archive');

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
}
