<?php

namespace Project\Console\Commands;

use Illuminate\Console\Command;
use Project\Support\UiDocs\SyncUiDocsHomeMain;

class SyncUiDocsHomeMainCommand extends Command
{
    protected $signature = 'project:sync-ui-docs-home-main';

    protected $description = 'Sync the site-specific WebBlocks UI docs home main narrative sections';

    public function __construct(private readonly SyncUiDocsHomeMain $sync)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->sync->run();
        $this->info('Synced WebBlocks UI docs home main narrative sections.');

        return self::SUCCESS;
    }
}
