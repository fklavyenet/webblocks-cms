<?php

namespace WebBlocks\Cms\Actions\EditorRecovery;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Policies\CmsResourcePolicy;
use WebBlocks\Cms\Policies\EditorRecoveryPolicy;
use WebBlocks\Cms\Queries\EditorRecovery\EditorRecoveryQuery;
use WebBlocks\Cms\Support\EditorRecovery\RecoveryFields;

final class WriteEditorRecovery
{
  public function __construct(private EditorRecoveryQuery $query) {}

  /** Lock order matches ACL mutations; shared ACL/page locks permit independent checkpoints. */
  public function authorizeLocked($user, Page $page, ?int $blockId, string $locale, bool $updatingPage = false): void
  {
    DB::table('wbcms_acl_locks')->where('id', 1)->sharedLock()->first();
    DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
    $pageQuery = DB::table('wbcms_pages')->where('id', $page->id);
    ($updatingPage ? $pageQuery->lockForUpdate() : $pageQuery->sharedLock())->first();
    app(EditorRecoveryPolicy::class)->authorizeContext($user->fresh(), Page::query()->findOrFail($page->id), $blockId, $locale);
  }

  public function checkpoint($user, Page $page, string $session, ?int $blockId, string $locale, array $data, array $entries): object
  {
    return DB::transaction(function () use ($user, $page, $session, $blockId, $locale, $data, $entries) {
      $this->authorizeLocked($user, $page, $blockId, $locale);
      app(CmsResourcePolicy::class)->authorizeFields(app(RecoveryFields::class)->input($entries), $user->fresh(), $page->site_id);
      DB::table('wbcms_editor_recoveries')->where('user_id', $user->id)->where('expires_at', '<=', now())->delete();
      $row = $this->query->find($user, $page, $session, $blockId, $locale, true);
      abort_if($row?->cleared, 410);
      if ($row && $row->sequence >= $data['sequence']) {
        return $row;
      }
      abort_if($row && $row->base_fingerprint !== $data['base_fingerprint'], 409);
      abort_if(! $row && DB::table('wbcms_editor_recoveries')->where('user_id', $user->id)->where('cleared', false)->count() >= 32, 429);
      $values = ['base_fingerprint' => $data['base_fingerprint'], 'payload_hash' => hash('sha256', json_encode(array_map(fn ($entry) => [$entry['name'], $entry['value']], $entries), JSON_THROW_ON_ERROR)), 'sequence' => $data['sequence'], 'updated_at' => now(), 'expires_at' => now()->addDays(7)];
      if ($row) {
        $id = $row->id;
        DB::table('wbcms_editor_recoveries')->where('id', $id)->update($values);
        DB::table('wbcms_editor_recovery_values')->where('recovery_id', $id)->delete();
      } else {
        $id = DB::table('wbcms_editor_recoveries')->insertGetId([...$values, 'user_id' => $user->id, 'page_id' => $page->id, 'site_id' => $page->site_id, 'block_id' => $blockId, 'locale' => $locale, 'editing_session' => $session, 'created_at' => now()]);
      }
      foreach ($entries as $position => $entry) {
        $valueId = DB::table('wbcms_editor_recovery_values')->insertGetId(['recovery_id' => $id, 'position' => $position, 'field_name' => $entry['name'], 'format' => $entry['nodes'] ? 'json' : ($entry['value'] === null ? 'absent' : 'text'), 'text_value' => $entry['nodes'] ? null : $entry['value']]);
        foreach (array_chunk($entry['nodes'] ?? [], 50) as $chunk) {
          DB::table('wbcms_editor_recovery_nodes')->insert(array_map(fn ($node) => ['value_id' => $valueId, ...$node], $chunk));
        }
      }

      return DB::table('wbcms_editor_recoveries')->find($id);
    });
  }

  public function clear($user, Page $page, string $session, ?int $blockId, string $locale): void
  {
    DB::transaction(function () use ($user, $page, $session, $blockId, $locale): void {
      $this->authorizeLocked($user, $page, $blockId, $locale);
      $row = $this->query->find($user, $page, $session, $blockId, $locale, true);
      if ($row) {
        DB::table('wbcms_editor_recoveries')->where('id', $row->id)->update(['cleared' => true, 'payload_hash' => null, 'updated_at' => now()]);
        DB::table('wbcms_editor_recovery_values')->where('recovery_id', $row->id)->delete();
      } else {
        DB::table('wbcms_editor_recoveries')->insert(['user_id' => $user->id, 'page_id' => $page->id, 'site_id' => $page->site_id, 'block_id' => $blockId, 'locale' => $locale, 'editing_session' => $session, 'base_fingerprint' => str_repeat('0', 64), 'sequence' => 0, 'cleared' => true, 'created_at' => now(), 'updated_at' => now(), 'expires_at' => now()->addDays(7)]);
      }
    });
  }

  public function expire(): int
  {
    if (! Schema::hasTable('wbcms_editor_recoveries')) {
      return 0;
    }

    return DB::table('wbcms_editor_recoveries')->where('expires_at', '<=', now())->delete();
  }
}
