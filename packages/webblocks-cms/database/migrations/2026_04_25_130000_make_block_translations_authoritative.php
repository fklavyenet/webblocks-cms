<?php

use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Support\Blocks\BlockTranslationWriter;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (! Locale::query()->where('is_default', true)->exists()) {
            return;
        }

        $writer = app(BlockTranslationWriter::class);

        Block::query()
            ->with(['textTranslations', 'buttonTranslations', 'imageTranslations', 'contactFormTranslations'])
            ->orderBy('id')
            ->get()
            ->each(fn (Block $block) => $writer->normalizeCanonicalStorage($block));
    }

    public function down(): void
    {
        // Phase 3 intentionally keeps compatibility columns in place but stops using them as the source of truth.
    }
};
