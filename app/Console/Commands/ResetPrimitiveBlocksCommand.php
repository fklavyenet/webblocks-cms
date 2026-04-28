<?php

namespace App\Console\Commands;

use App\Models\Block;
use App\Models\BlockType;
use Illuminate\Console\Command;

class ResetPrimitiveBlocksCommand extends Command
{
    protected $signature = 'cms:reset-primitive-blocks {--dry-run : Show how many non-primitive blocks would be deleted}';

    protected $description = 'Delete non-primitive page blocks for the minimal header/plain text foundation';

    public function handle(): int
    {
        $primitiveTypeIds = BlockType::query()
            ->whereIn('slug', ['header', 'plain_text'])
            ->pluck('id')
            ->all();

        $query = Block::query();

        if ($primitiveTypeIds !== []) {
            $query->whereNotIn('block_type_id', $primitiveTypeIds);
        }

        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("{$count} non-primitive block rows would be deleted.");

            return self::SUCCESS;
        }

        $query->delete();

        $this->info("Deleted {$count} non-primitive block rows.");

        return self::SUCCESS;
    }
}
