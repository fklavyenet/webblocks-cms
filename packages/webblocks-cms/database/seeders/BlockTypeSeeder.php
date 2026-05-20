<?php

namespace WebBlocks\Cms\Database\Seeders;

use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use Illuminate\Database\Seeder;
use RuntimeException;
use WebBlocks\Cms\Support\Blocks\CoreBlockTypeCatalogSyncer;

class BlockTypeSeeder extends Seeder
{
    public function __construct(
        private readonly CoreBlockTypeCatalogSyncer $syncer,
    ) {}

    public function run(): void
    {
        $activeSlugs = $this->syncer->slugs();

        BlockType::query()
            ->whereNotIn('slug', $activeSlugs)
            ->update(['status' => 'draft']);

        $this->syncer->sync();
        $this->deleteLegacyHeadingBlockType();
    }

    private function deleteLegacyHeadingBlockType(): void
    {
        $headingBlockType = BlockType::query()->where('slug', 'heading')->first();

        if (! $headingBlockType) {
            return;
        }

        $liveHeadingCount = Block::query()
            ->where(function ($query) use ($headingBlockType): void {
                $query->where('type', 'heading')
                    ->orWhere('block_type_id', $headingBlockType->id);
            })
            ->where('status', 'published')
            ->count();

        if ($liveHeadingCount > 0) {
            throw new RuntimeException('Cannot remove legacy block type [heading] because '.$liveHeadingCount.' live block(s) still reference it. Move those blocks to the canonical [header] type before running BlockTypeSeeder again.');
        }

        $headingBlockType->delete();
    }
}
