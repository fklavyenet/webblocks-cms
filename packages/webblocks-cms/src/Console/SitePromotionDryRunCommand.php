<?php

namespace WebBlocks\Cms\Console;

use App\Models\Site;
use App\Support\SitePromotion\SitePromotionOptions;
use App\Support\SitePromotion\SitePromotionPackageInspector;
use App\Support\SitePromotion\SitePromotionPlanner;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class SitePromotionDryRunCommand extends Command
{
    protected $signature = 'site-promotion:dry-run
        {archive : Absolute or relative path to a site promotion package zip}
        {--target-site= : Target site id, handle, or domain}
        {--strategy=additive_update : Promotion strategy}
        {--apply-assets : Apply physical media and public /site files if present}';

    protected $description = 'Create a dry run plan for promoting a site package into an existing target site';

    public function __construct(
        private readonly SitePromotionPackageInspector $inspector,
        private readonly SitePromotionPlanner $planner,
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

        $targetSite = $this->resolveSite((string) $this->option('target-site'));

        if (! $targetSite instanceof Site) {
            $this->error('Target site was not found.');

            return self::FAILURE;
        }

        try {
            $inspection = $this->inspector->inspectUpload(new UploadedFile($archivePath, basename($archivePath), 'application/zip', null, true));
            $plan = $this->planner->plan($inspection->archivePath, SitePromotionOptions::fromArray([
                'target_site_id' => $targetSite->id,
                'strategy' => (string) $this->option('strategy'),
                'apply_assets' => (bool) $this->option('apply-assets'),
            ]));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line('Dry run token: '.$plan->token);
        $this->line('Target site: '.$targetSite->handle);
        $this->line('Strategy: '.$plan->strategy());
        $this->line('Pages create/update/archive: '.$plan->summary['pages_to_create'].'/'.$plan->summary['pages_to_update'].'/'.$plan->summary['pages_to_archive']);

        if ($plan->warnings !== []) {
            foreach ($plan->warnings as $warning) {
                $this->warn($warning);
            }
        }

        if ($plan->errors !== []) {
            foreach ($plan->errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function resolveSite(string $value): ?Site
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return Site::query()
            ->where(function ($query) use ($value) {
                if (ctype_digit($value)) {
                    $query->whereKey((int) $value);
                }

                $query->orWhere('handle', $value)
                    ->orWhere('name', $value)
                    ->orWhere('domain', $value);
            })
            ->first();
    }
}
