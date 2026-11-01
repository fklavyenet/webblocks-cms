<?php

namespace WebBlocks\Cms\Support\Revisions;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockMedia;
use WebBlocks\Cms\Support\Relational\PersistentSchema;

final class RevisionIdentitySchema
{
  public static function ensure(): void
  {
    foreach (['wbcms_blocks' => 'page_id', 'wbcms_block_media' => 'block_id'] as $name => $owner) {
      if (! Schema::hasTable($name)) {
        continue;
      }
      if (! Schema::hasColumn($name, 'revision_key')) {
        Schema::table($name, fn (Blueprint $table) => $table->uuid('revision_key')->nullable());
      }
      DB::table($name)->whereNull('revision_key')->select('id')->chunkById(500, function ($rows) use ($name): void {
        foreach ($rows as $row) {
          // Do not fire content events, touch timestamps or rewrite old snapshots.
          DB::table($name)->where('id', $row->id)->whereNull('revision_key')->update(['revision_key' => (string) Str::uuid()]);
        }
      });
      $index = $name.'_revision_identity';
      if (! Schema::hasIndex($name, $index)) {
        Schema::table($name, fn (Blueprint $table) => $table->unique([$owner, 'revision_key'], $index));
      }
    }
    // Adds declared scalar fields only; existing snapshot nodes/hash stay intact.
    PersistentSchema::ensure();
    Block::clearRevisionIdentitySchemaCache();
    BlockMedia::clearRevisionIdentitySchemaCache();
  }
}
