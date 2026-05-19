<?php

namespace WebBlocks\Cms\Console;

use App\Support\Icons\WebBlocksIconManifestSyncer;
use Illuminate\Console\Command;
use Throwable;

class SyncWebBlocksUiIconsCommand extends Command
{
    protected $signature = 'icons:sync-webblocks-ui {--manifest=} {--deactivate-missing}';

    protected $description = 'Sync the icon catalog from the WebBlocks UI icon manifest';

    public function __construct(private readonly WebBlocksIconManifestSyncer $syncer)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $summary = $this->syncer->sync(
                manifest: $this->option('manifest'),
                deactivateMissing: (bool) $this->option('deactivate-missing'),
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line('Manifest: '.$summary['manifest']);
        $this->line('Created: '.$summary['created']);
        $this->line('Updated: '.$summary['updated']);
        $this->line('Unchanged: '.$summary['unchanged']);
        $this->line('Deactivated: '.$summary['deactivated']);

        return self::SUCCESS;
    }
}
