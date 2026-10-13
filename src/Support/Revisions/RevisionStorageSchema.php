<?php

namespace WebBlocks\Cms\Support\Revisions;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class RevisionStorageSchema
{
  public static function ensure(): void
  {
    foreach (['wbcms_page_revisions' => 'page_id', 'wbcms_shared_slot_revisions' => 'shared_slot_id'] as $name => $ownerKey) {
      if (! Schema::hasTable($name)) {
        continue;
      }
      $hashMissing = ! Schema::hasColumn($name, 'snapshot_hash');
      $bytesMissing = ! Schema::hasColumn($name, 'snapshot_bytes');
      Schema::table($name, function (Blueprint $table) use ($name, $ownerKey, $hashMissing, $bytesMissing): void {
        if ($hashMissing) {
          $table->char('snapshot_hash', 64)->nullable();
        }
        if ($bytesMissing) {
          $table->unsignedBigInteger('snapshot_bytes')->nullable();
        }
        $index = $name.'_cleanup_order';
        if (! Schema::hasIndex($name, $index)) {
          $table->index([$ownerKey, 'created_at', 'id', 'snapshot_bytes'], $index);
        }
        $sizeIndex = $name.'_site_sizes';
        if (! Schema::hasIndex($name, $sizeIndex)) {
          $table->index(['site_id', 'snapshot_bytes'], $sizeIndex);
        }
        $missingIndex = $name.'_missing_sizes';
        if (! Schema::hasIndex($name, $missingIndex)) {
          $table->index(['snapshot_bytes', 'id', 'site_id'], $missingIndex);
        }
        $latestIndex = $name.'_latest_hash';
        if (! Schema::hasIndex($name, $latestIndex)) {
          $table->index([$ownerKey, 'id', 'snapshot_hash'], $latestIndex);
        }
      });
      // Upgrade historical rows without loading or sorting their JSON payloads.
      $highWater = (int) DB::table($name)->max('id');
      DB::table($name)->whereNull('snapshot_bytes')->where('id', '<=', $highWater)->select('id')->chunkById(500, function ($rows) use ($name): void {
        DB::table($name)->whereIn('id', $rows->pluck('id'))->whereNull('snapshot_bytes')
          ->update(['snapshot_bytes' => DB::raw('LENGTH(snapshot)')]);
      });
    }

    if (Schema::hasTable('wbcms_page_revisions') && Schema::hasTable('wbcms_shared_slot_revisions') && ! Schema::hasTable('wbcms_revision_capture_events')) {
      Schema::create('wbcms_revision_capture_events', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('page_revision_id')->nullable()->constrained('wbcms_page_revisions')->cascadeOnDelete();
        $table->foreignId('shared_slot_revision_id')->nullable()->constrained('wbcms_shared_slot_revisions')->cascadeOnDelete();
        $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        $table->string('source')->nullable();
        $table->string('event')->nullable();
        $table->string('label')->nullable();
        $table->text('reason')->nullable();
        $table->boolean('snapshot_created');
        $table->timestamps();
        $table->index(['page_revision_id', 'id'], 'revision_capture_page_order');
        $table->index(['shared_slot_revision_id', 'id'], 'revision_capture_shared_order');
      });
    }
  }
}
