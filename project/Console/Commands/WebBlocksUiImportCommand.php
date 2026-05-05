<?php

namespace Project\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use Project\Support\UiDocs\WebBlocksUiImporter;

class WebBlocksUiImportCommand extends Command
{
    protected $signature = 'project:webblocksui-import {key : Project-layer WebBlocks UI payload key}';

    protected $description = 'Import project-layer WebBlocks UI page payloads from storage/project/webblocksui.com';

    public function __construct(private readonly WebBlocksUiImporter $importer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $result = $this->importer->run((string) $this->argument('key'));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        foreach ($result as $line) {
            $this->line($line);
        }

        $this->info('WebBlocks UI project import completed.');

        return self::SUCCESS;
    }
}
