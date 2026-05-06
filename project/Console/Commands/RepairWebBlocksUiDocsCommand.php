<?php

namespace Project\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use Project\Support\UiDocs\RepairWebBlocksUiDocsEnvironment;

class RepairWebBlocksUiDocsCommand extends Command
{
    protected $signature = 'project:webblocksui-repair';

    protected $description = 'Repair project-layer WebBlocks UI docs shared-slot assignments and clean accidental local debug artifacts';

    public function __construct(private readonly RepairWebBlocksUiDocsEnvironment $repair)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->repair->run();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($result as $line) {
            $this->line($line);
        }

        $this->info('WebBlocks UI docs repair completed.');

        return self::SUCCESS;
    }
}
