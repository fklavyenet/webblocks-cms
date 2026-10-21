<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\Relational\StructuredSettings;
use WebBlocks\Cms\Support\Relational\StructuredSettingsSchema;

return new class extends Migration
{
  public function up(): void
  {
    StructuredSettingsSchema::ensure();
    // Rebind only documents containing newly declared fields. Typed tree values,
    // key order and unknown extensions are read before rewriting, without casts.
    DB::table('wbcms_setting_documents')->whereNotNull('block_id')->where('attribute', 'settings')
      ->whereIn('id', DB::table('wbcms_setting_nodes')->whereIn('key_text', ['sort_order', 'show_approved', 'show_author_name', 'store_submissions'])->select('document_id'))
      ->orderBy('id')->chunkById(100, function ($documents): void {
        foreach ($documents as $document) {
          DB::transaction(function () use ($document): void {
            $block = Block::query()->whereKey($document->block_id)->lockForUpdate()->first();
            if ($block) {
              $storage = app(StructuredSettings::class);
              [$exists, $value] = $storage->read($block, 'settings');
              if ($exists) {
                $storage->write($block, 'settings', $value);
              }
            }
          });
        }
      });
  }

  public function down(): void
  {
    // Additive: removing columns would discard values used by older renderers.
  }
};
