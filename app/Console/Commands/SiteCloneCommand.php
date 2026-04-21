<?php

namespace App\Console\Commands;

use App\Support\Sites\SiteCloneOptions;
use App\Support\Sites\SiteCloneService;
use Illuminate\Console\Command;
use RuntimeException;

class SiteCloneCommand extends Command
{
    protected $signature = 'site:clone
        {source : Source site id, handle, name, or domain}
        {target : Target site id, handle, name, or domain}
        {--target-name= : Target site display name when creating or updating}
        {--target-handle= : Target site handle when creating or updating}
        {--target-domain= : Target site domain/host}
        {--with-navigation : Clone site navigation items}
        {--without-navigation : Skip site navigation items}
        {--with-media : Clone asset references used by cloned blocks}
        {--without-media : Skip asset references and block assets}
        {--copy-media-files : Duplicate asset records and physical files instead of linking existing shared assets}
        {--with-translations : Clone page and block translation rows}
        {--without-translations : Skip non-canonical translation rows}
        {--overwrite-target : Replace existing target site content before cloning}
        {--dry-run : Validate and summarize without writing}';

    protected $description = 'Clone site content into another site within the same install';

    public function __construct(
        private readonly SiteCloneService $siteCloneService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->siteCloneService->clone(
                source: $this->argument('source'),
                target: $this->argument('target'),
                options: $this->optionsFromInput(),
            );
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line('source resolved: '.$result->sourceSite->name.' (#'.$result->sourceSite->id.')');
        $this->line('target resolved: '.($result->targetSite?->name ?? 'unknown').' (#'.($result->targetSite?->id ?? 'new').')');
        $this->line('target created: '.($result->targetCreated ? 'yes' : 'no'));
        $this->line('pages cloned: '.$result->count('pages_cloned'));
        $this->line('page translations cloned: '.$result->count('page_translations_cloned'));
        $this->line('page slots cloned: '.$result->count('page_slots_cloned'));
        $this->line('blocks cloned: '.$result->count('blocks_cloned'));
        $this->line('block translation rows cloned: '.$result->count('block_translation_rows_cloned'));
        $this->line('navigation items cloned: '.$result->count('navigation_items_cloned'));
        $this->line('assets linked: '.$result->count('assets_linked'));
        $this->line('assets cloned: '.$result->count('assets_cloned'));
        $this->line('block asset links cloned: '.$result->count('block_asset_links_cloned'));
        $this->line('files copied: '.$result->count('files_copied'));
        $this->line('target domain set: '.($result->targetDomain() ?: 'not set'));
        $this->line('dry-run: '.($result->dryRun ? 'yes' : 'no'));

        return self::SUCCESS;
    }

    private function optionsFromInput(): SiteCloneOptions
    {
        return SiteCloneOptions::fromArray([
            'target_name' => $this->option('target-name'),
            'target_handle' => $this->option('target-handle'),
            'target_domain' => $this->option('target-domain'),
            'with_navigation' => $this->booleanOption('with-navigation', 'without-navigation', true),
            'with_media' => $this->booleanOption('with-media', 'without-media', true),
            'copy_media_files' => (bool) $this->option('copy-media-files'),
            'with_translations' => $this->booleanOption('with-translations', 'without-translations', true),
            'overwrite_target' => (bool) $this->option('overwrite-target'),
            'dry_run' => (bool) $this->option('dry-run'),
        ]);
    }

    private function booleanOption(string $positive, string $negative, bool $default): bool
    {
        if ($this->option($negative)) {
            return false;
        }

        if ($this->option($positive)) {
            return true;
        }

        return $default;
    }
}
