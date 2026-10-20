<?php

namespace WebBlocks\Cms\Queries\EditorRecovery;

use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Support\Relational\SettingTree;

final class EditorRecoveryQuery
{
  public function find($user, Page $page, string $session, ?int $blockId, string $locale, bool $lock = false): ?object
  {
    $query = DB::table('wbcms_editor_recoveries')->where('user_id', $user->id)->where('editing_session', $session);
    $row = ($lock ? $query->lockForUpdate() : $query)->first();
    if ($row) {
      abort_unless((int) $row->page_id === (int) $page->id && (int) $row->site_id === (int) $page->site_id && ($row->block_id === null ? null : (int) $row->block_id) === $blockId && $row->locale === $locale, 404);
    }

    return $row;
  }

  public function entries(int $id): array
  {
    $values = DB::table('wbcms_editor_recovery_values')->where('recovery_id', $id)->orderBy('position')->get();
    $nodes = DB::table('wbcms_editor_recovery_nodes')->whereIn('value_id', $values->pluck('id'))->orderBy('node_index')->get()->groupBy('value_id');

    return $values->map(fn ($value) => [
      'name' => $value->field_name,
      'value' => match ($value->format) {
        'absent' => null,
        'json' => json_encode(SettingTree::decode(($nodes[$value->id] ?? collect())->all(), [], null), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        default => $value->text_value,
      },
    ])->all();
  }
}
