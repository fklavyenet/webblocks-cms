<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up(): void
  {
    if (! Schema::hasTable('wbcms_media_folders') || ! Schema::hasTable('wbcms_media')) {
      return;
    }

    DB::transaction(function (): void {
      $folders = DB::table('wbcms_media_folders')->orderBy('id')->get()->keyBy('id');
      $canonicalIds = [];
      $canonicalBySiblingName = [];
      $resolving = [];

      $resolve = function (int $id) use (&$resolve, $folders, &$canonicalIds, &$canonicalBySiblingName, &$resolving): int {
        if (isset($canonicalIds[$id])) {
          return $canonicalIds[$id];
        }

        // Preserve malformed cyclic legacy data instead of making a repair
        // migration fail an otherwise valid CMS update.
        if (isset($resolving[$id])) {
          return $id;
        }

        $folder = $folders->get($id);
        if (! $folder) {
          return $id;
        }

        $resolving[$id] = true;
        $parentId = $folder->parent_id === null ? null : $resolve((int) $folder->parent_id);
        unset($resolving[$id]);

        $key = ($parentId ?? 'root').'\0'.mb_strtolower(trim((string) $folder->name));
        $canonicalId = $canonicalBySiblingName[$key] ?? null;

        if ($canonicalId === null) {
          $canonicalBySiblingName[$key] = $id;
          $canonicalIds[$id] = $id;

          if ($folder->parent_id !== $parentId) {
            DB::table('wbcms_media_folders')->where('id', $id)->update(['parent_id' => $parentId]);
          }

          return $id;
        }

        DB::table('wbcms_media')->where('folder_id', $id)->update(['folder_id' => $canonicalId]);
        DB::table('wbcms_media_folders')->where('parent_id', $id)->update(['parent_id' => $canonicalId]);
        DB::table('wbcms_media_folders')->where('id', $id)->delete();

        return $canonicalIds[$id] = $canonicalId;
      };

      foreach ($folders->keys() as $id) {
        $resolve((int) $id);
      }
    });
  }

  public function down(): void
  {
    // Merged duplicate rows cannot be reconstructed reliably.
  }
};
