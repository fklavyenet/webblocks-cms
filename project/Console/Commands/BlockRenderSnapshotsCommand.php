<?php

namespace Project\Console\Commands;

use Illuminate\Console\Command;
use Project\Support\BlockRenderSnapshots\BlockRenderSnapshotGenerator;

class BlockRenderSnapshotsCommand extends Command
{
    protected $signature = 'project:block-render-snapshots';

    protected $description = 'Generate reviewable HTML snapshots for every published block type';

    public function __construct(
        private readonly BlockRenderSnapshotGenerator $generator,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->generator->run();

        $this->info('Block render snapshots generated.');
        $this->line('Output directory: '.$result['output_directory']);
        $this->line('Published block types processed: '.(string) $result['processed_count']);
        $this->line('Rendered successfully: '.(string) $result['rendered_count']);
        $this->line('Warnings/errors: '.(string) $result['warning_count']);

        return self::SUCCESS;
    }
}
