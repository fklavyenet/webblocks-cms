<?php

namespace App\Console\Commands;

use App\Support\Blocks\CoreBlockTypeCatalogSyncer;
use Illuminate\Console\Command;

class SyncCoreBlockTypesCommand extends Command
{
    protected $signature = 'block-types:sync-core {--force : Allow running in production contexts}';

    protected $description = 'Synchronize the core CMS block type catalog';

    public function __construct(
        private readonly CoreBlockTypeCatalogSyncer $syncer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $summary = $this->syncer->sync();

        $this->info('Core block type catalog synchronized.');
        $this->line('Created: '.$summary['created']);
        $this->line('Updated: '.$summary['updated']);
        $this->line('Unchanged: '.$summary['unchanged']);
        $this->line('Skipped: '.$summary['skipped']);

        return self::SUCCESS;
    }
}
