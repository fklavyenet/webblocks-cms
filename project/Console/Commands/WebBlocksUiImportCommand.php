<?php

namespace Project\Console\Commands;

use Illuminate\Console\Command;
use Project\Support\UiDocs\ImportRemainingWebBlocksUiDocsHtml;
use Project\Support\UiDocs\WebBlocksUiImporter;
use RuntimeException;

class WebBlocksUiImportCommand extends Command
{
    protected $signature = 'project:webblocksui-import {key : Project-layer WebBlocks UI payload key} {--force-html : Replace existing fast HTML imported pages for the requested remaining docs batch}';

    protected $description = 'Import project-layer WebBlocks UI page payloads from storage/project/webblocksui.com';

    public function __construct(
        private readonly WebBlocksUiImporter $importer,
        private readonly ImportRemainingWebBlocksUiDocsHtml $remainingHtmlImporter,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $key = (string) $this->argument('key');
            $result = match ($key) {
                'remaining-docs-html', 'docs-html-remaining' => $this->remainingHtmlImporter->run((bool) $this->option('force-html')),
                default => $this->importer->run($key),
            };
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
