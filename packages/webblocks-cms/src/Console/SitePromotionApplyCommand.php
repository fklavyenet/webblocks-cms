<?php

namespace WebBlocks\Cms\Console;

use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\SitePromotion\SitePromotionApplier;
use WebBlocks\Cms\Support\SitePromotion\SitePromotionOptions;
use WebBlocks\Cms\Support\SitePromotion\SitePromotionPackageInspector;
use WebBlocks\Cms\Support\SitePromotion\SitePromotionPlanner;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use RuntimeException;

class SitePromotionApplyCommand extends Command
{
    protected $signature = 'site-promotion:apply
        {archive : Absolute or relative path to a site promotion package zip}
        {--target-site= : Target site id, handle, or domain}
        {--strategy=additive_update : Promotion strategy}
        {--apply-assets : Apply physical media and public /site files if present}
        {--force : Skip the confirmation prompt}';

    protected $description = 'Apply a validated site promotion package into an existing target site';

    public function __construct(
        private readonly SitePromotionPackageInspector $inspector,
        private readonly SitePromotionPlanner $planner,
        private readonly SitePromotionApplier $applier,
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

        if ($plan->errors !== []) {
            foreach ($plan->errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('Apply this Site Promotion plan to ['.$targetSite->handle.']?', false)) {
            $this->line('Site Promotion cancelled.');

            return self::SUCCESS;
        }

        try {
            $result = $this->applier->apply($plan->token);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Site Promotion applied successfully.');
        $this->line('Safety backup: #'.($result->safetyBackup?->id ?? '-'));
        $this->line('Search indexed: '.$result->searchIndexed);
        $this->line('Search skipped: '.$result->searchSkipped);

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
