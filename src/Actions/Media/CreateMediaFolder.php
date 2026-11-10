<?php

namespace WebBlocks\Cms\Actions\Media;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use WebBlocks\Cms\Models\MediaFolder;

final class CreateMediaFolder
{
  public static function slug(string $name, string $slug): ?string
  {
    return Str::slug($slug !== '' ? $slug : $name) ?: null;
  }

  /** Return the existing folder when a sibling already has this name. */
  public function execute(string $name, ?string $slug, ?int $parentId): MediaFolder
  {
    $name = trim($name);

    return DB::transaction(function () use ($name, $slug, $parentId): MediaFolder {
      $siblings = MediaFolder::query()->where('parent_id', $parentId);
      $existing = $siblings->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->lockForUpdate()->first();

      return $existing ?? MediaFolder::create(['name' => $name, 'slug' => $slug, 'parent_id' => $parentId]);
    }, attempts: 3);
  }
}
