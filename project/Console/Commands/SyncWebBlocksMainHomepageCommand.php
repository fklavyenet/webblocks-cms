<?php

namespace Project\Console\Commands;

use Illuminate\Console\Command;
use Project\Support\WebBlocksMain\SyncWebBlocksMainHomepage;

class SyncWebBlocksMainHomepageCommand extends Command
{
    protected $signature = 'project:sync-webblocks-main-homepage';

    protected $description = 'Create or update the webblocksui.com main homepage';

    public function __construct(private readonly SyncWebBlocksMainHomepage $sync)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        foreach ($this->sync->run() as $line) {
            $this->line($line);
        }

        return self::SUCCESS;
    }
}
