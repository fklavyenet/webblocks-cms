<?php

namespace Project\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use Project\Support\UiDocs\SetupWebBlocksUiDocsSite;

class SetupWebBlocksUiDocsSiteCommand extends Command
{
    protected $signature = 'project:webblocksui-setup-site';

    protected $description = 'Create the project-layer WebBlocks UI docs site and required page dependencies';

    public function __construct(private readonly SetupWebBlocksUiDocsSite $setup)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->setup->run();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($result as $line) {
            $this->line($line);
        }

        $this->info('WebBlocks UI docs site setup completed.');

        return self::SUCCESS;
    }
}
