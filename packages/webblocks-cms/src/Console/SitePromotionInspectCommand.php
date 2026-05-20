<?php

namespace WebBlocks\Cms\Console;

use App\Support\SitePromotion\SitePromotionPackageInspector;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class SitePromotionInspectCommand extends Command
{
    protected $signature = 'site-promotion:inspect
        {archive : Absolute or relative path to a site promotion package zip}';

    protected $description = 'Inspect a site promotion package and print the portable source summary';

    public function __construct(
        private readonly SitePromotionPackageInspector $inspector,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $archivePath = (string) $this->argument('archive');

        if (! is_file($archivePath)) {
            $this->error('Promotion archive file was not found.');

            return self::FAILURE;
        }

        try {
            $inspection = $this->inspector->inspectUpload(new UploadedFile($archivePath, basename($archivePath), 'application/zip', null, true));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $source = $inspection->sourceSite();
        $this->line('Package inspected successfully.');
        $this->line('Source site: '.($source['name'] ?? '-'));
        $this->line('Handle: '.($source['handle'] ?? '-'));
        $this->line('Package type: '.($source['package_type'] ?? '-'));
        $this->line('Includes assets: '.($inspection->includesAssets ? 'yes' : 'no'));

        return self::SUCCESS;
    }
}
