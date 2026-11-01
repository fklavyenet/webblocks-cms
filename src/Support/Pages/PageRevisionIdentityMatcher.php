<?php

namespace WebBlocks\Cms\Support\Pages;

use Illuminate\Support\Str;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageRevision;

/** Read-only foundation for scoped restoration; never guesses by copy or order. */
final class PageRevisionIdentityMatcher
{
  public function match(Page $page, PageRevision $revision): array
  {
    $result = ['compatible' => false, 'blocks' => [], 'media' => [], 'issues' => []];
    if ((int) $revision->page_id !== (int) $page->id || (int) $revision->site_id !== (int) $page->site_id) {
      $result['issues'][] = ['code' => 'revision_owner_mismatch'];

      return $result;
    }
    $snapshot = $revision->snapshot ?? [];
    if (! is_array($snapshot)) {
      $result['issues'][] = ['code' => 'unsupported_snapshot_schema'];

      return $result;
    }
    $version = $snapshot['schema_version'] ?? null;
    if (! in_array($version, [1, 2], true) || ! is_array($snapshot['blocks'] ?? null)) {
      $result['issues'][] = ['code' => 'unsupported_snapshot_schema'];

      return $result;
    }
    $historical = $snapshot['blocks'];
    foreach ($historical as $entry) {
      if (! is_array($entry) || ! is_array($entry['block_media'] ?? [])
        || array_filter($entry['block_media'] ?? [], fn ($item) => ! is_array($item)) !== []) {
        $result['issues'][] = ['code' => 'malformed_snapshot_blocks'];

        return $result;
      }
    }
    $current = $page->blocks()->with('blockAssets')->get();
    $byKey = $current->groupBy('revision_key');
    $byId = $current->keyBy('id');
    $keyCounts = $this->counts($historical, 'revision_key');
    $idCounts = $this->counts($historical, 'snapshot_id');

    foreach ($historical as $entry) {
      $id = $entry['snapshot_id'] ?? null;
      if ((! is_int($id) && (! is_string($id) || ! ctype_digit($id))) || (int) $id < 1 || (string) (int) $id !== (string) $id || ($idCounts[(string) $id] ?? 0) !== 1) {
        $result['issues'][] = ['code' => 'invalid_snapshot_block_id'];

        continue;
      }
      $key = $entry['revision_key'] ?? null;
      if (array_key_exists('revision_key', $entry) || $version === 2) {
        if (! is_string($key) || ! Str::isUuid($key) || ($keyCounts[$key] ?? 0) !== 1) {
          $result['issues'][] = ['code' => 'invalid_snapshot_block_identity', 'snapshot_id' => (int) $id];

          continue;
        }
        $candidates = $byKey->get($key, collect());
        $block = $candidates->count() === 1 ? $candidates->first() : null;
      } else {
        // An original database row still alive on this source page is the only
        // supported v1 evidence. Recreated rows, clones and lookalikes do not match.
        $block = $byId->get((int) $id);
      }
      if (! $block) {
        $result['issues'][] = ['code' => 'block_identity_not_found', 'snapshot_id' => (int) $id];

        continue;
      }
      if ($block->type !== ($entry['type'] ?? null) || $block->source_type !== ($entry['source_type'] ?? null)
        || (int) $block->block_type_id !== (int) ($entry['block_type_id'] ?? 0)) {
        $result['issues'][] = ['code' => 'block_type_changed', 'snapshot_id' => (int) $id];

        continue;
      }
      $result['blocks'][(int) $id] = (int) $block->id;
    }

    foreach ($historical as $entry) {
      $id = (int) ($entry['snapshot_id'] ?? 0);
      $currentId = $result['blocks'][$id] ?? null;
      if ($currentId === null) {
        continue;
      }
      $block = $byId->get($currentId);
      $parent = $entry['parent_snapshot_id'] ?? null;
      $expectedParent = $parent === null ? null : ($result['blocks'][(int) $parent] ?? -1);
      if (($block->parent_id === null ? null : (int) $block->parent_id) !== $expectedParent) {
        $result['issues'][] = ['code' => 'block_parent_changed', 'snapshot_id' => $id];
      }
      if ((int) $block->slot_type_id !== (int) ($entry['slot_type_id'] ?? 0)
        || $block->slot !== ($entry['slot'] ?? null) || (int) $block->sort_order !== (int) ($entry['sort_order'] ?? 0)) {
        $result['issues'][] = ['code' => 'block_placement_changed', 'snapshot_id' => $id];
      }
      if ((int) $block->media_id !== (int) ($entry['media_id'] ?? $entry['asset_id'] ?? 0)) {
        $result['issues'][] = ['code' => 'block_media_assignment_changed', 'snapshot_id' => $id];
      }
      $this->matchMedia($block, $entry, $version, $result);
    }
    foreach ($current as $block) {
      if (! in_array((int) $block->id, $result['blocks'], true)) {
        $result['issues'][] = ['code' => 'unmatched_current_block', 'block_id' => (int) $block->id];
      }
    }
    $result['compatible'] = $result['issues'] === [];

    return $result;
  }

  private function matchMedia(Block $block, array $entry, int $version, array &$result): void
  {
    $id = (int) $entry['snapshot_id'];
    $items = $entry['block_media'] ?? [];
    $current = $block->blockAssets;
    $byKey = $current->groupBy('revision_key');
    $counts = $this->counts($items, 'revision_key');
    $matchedIds = [];
    foreach ($items as $index => $item) {
      $key = $item['revision_key'] ?? null;
      if (! is_string($key) || ! Str::isUuid($key) || ($counts[$key] ?? 0) !== 1) {
        $result['issues'][] = ['code' => $version === 1 && $key === null ? 'legacy_media_identity_missing' : 'invalid_snapshot_media_identity', 'snapshot_id' => $id, 'item_index' => $index];

        continue;
      }
      $candidates = $byKey->get($key, collect());
      if ($candidates->count() !== 1) {
        $result['issues'][] = ['code' => 'media_identity_not_found', 'snapshot_id' => $id, 'item_index' => $index];

        continue;
      }
      $media = $candidates->first();
      $result['media'][$id][$index] = (int) $media->id;
      $matchedIds[] = (int) $media->id;
      if ((int) $media->media_id !== (int) ($item['media_id'] ?? 0) || $media->role !== ($item['role'] ?? null)) {
        $result['issues'][] = ['code' => 'media_assignment_changed', 'snapshot_id' => $id, 'item_index' => $index];
      }
      if ((int) $media->position !== (int) ($item['position'] ?? 0)) {
        $result['issues'][] = ['code' => 'media_placement_changed', 'snapshot_id' => $id, 'item_index' => $index];
      }
    }
    foreach ($current as $media) {
      if (! in_array((int) $media->id, $matchedIds, true)) {
        $result['issues'][] = ['code' => 'unmatched_current_media', 'snapshot_id' => $id, 'block_media_id' => (int) $media->id];
      }
    }
  }

  private function counts(array $entries, string $field): array
  {
    return array_count_values(array_filter(array_column($entries, $field), fn ($value) => is_string($value) || is_int($value)));
  }
}
