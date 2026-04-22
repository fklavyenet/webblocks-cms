<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Support\Sites\ExportImport\SiteExportManager;
use Illuminate\Console\Command;
use RuntimeException;

class SiteExportCommand extends Command
{
    protected $signature = 'site:export
        {site : Site id, handle, name, or domain}
        {--with-media : Include referenced media/assets and files}';

    protected $description = 'Export one site into a portable site export package';

    public function __construct(
        private readonly SiteExportManager $siteExportManager,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $identifier = (string) $this->argument('site');
        $site = Site::query()
            ->whereKey(is_numeric($identifier) ? (int) $identifier : null)
            ->orWhere('handle', str($identifier)->slug()->toString())
            ->orWhere('name', $identifier)
            ->orWhere('domain', $identifier)
            ->first();

        if (! $site) {
            $this->error('Site could not be resolved.');

            return self::FAILURE;
        }

        try {
            $siteExport = $this->siteExportManager->export($site, (bool) $this->option('with-media'));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line('site exported: '.$site->name.' (#'.$site->id.')');
        $this->line('archive: '.$siteExport->archive_path);
        $this->line('includes media: '.($siteExport->includes_media ? 'yes' : 'no'));

        foreach (($siteExport->summary_json ?? []) as $label => $value) {
            $this->line(str_replace('_', ' ', $label).': '.$value);
        }

        return self::SUCCESS;
    }
}
