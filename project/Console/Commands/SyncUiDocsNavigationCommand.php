<?php

namespace Project\Console\Commands;

use Illuminate\Console\Command;
use Project\Support\UiDocs\SyncUiDocsNavigation;

class SyncUiDocsNavigationCommand extends Command
{
    protected $signature = 'project:sync-ui-docs-navigation';

    protected $description = 'Sync the site-specific WebBlocks UI docs navigation menu';

    public function __construct(private readonly SyncUiDocsNavigation $sync)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->sync->run();
        $this->info('Synced WebBlocks UI docs navigation.');

        return self::SUCCESS;
    }
}
