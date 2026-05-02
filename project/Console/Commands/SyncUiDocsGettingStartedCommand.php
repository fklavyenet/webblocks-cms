<?php

namespace Project\Console\Commands;

use Illuminate\Console\Command;
use Project\Support\UiDocs\SyncUiDocsGettingStarted;

class SyncUiDocsGettingStartedCommand extends Command
{
    protected $signature = 'project:sync-ui-docs-getting-started';

    protected $description = 'Sync the site-specific WebBlocks UI Getting Started docs page';

    public function __construct(private readonly SyncUiDocsGettingStarted $sync)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->sync->run();
        $this->info('Synced WebBlocks UI Getting Started docs page.');

        return self::SUCCESS;
    }
}
