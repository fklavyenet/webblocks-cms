<?php

namespace WebBlocks\Cms\Support\Pages;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageRevision;

class PageRevisionInspector
{
  public function __construct(private readonly PageRevisionManager $revisionManager) {}

  public function inspect(Page $page, PageRevision $revision): array
  {
    $selected = $revision->snapshot ?? [];
    $current = $this->revisionManager->snapshotForInspection($page);

    return [
      'comparison' => $this->comparison($current, $selected),
      'changes' => $this->changes($current, $selected),
      'health' => $this->health($selected),
      'snapshot' => $this->snapshotSummary($selected),
    ];
  }

  public function listSummary(PageRevision $revision, ?PageRevision $previous): array
  {
    if (! $previous) {
      return ['type' => 'initial', 'categories' => [], 'extra' => 0];
    }

    $changes = collect($this->changes($previous->snapshot ?? [], $revision->snapshot ?? []))
      ->filter(fn (array $change): bool => $change['changed'])
      ->map(fn (array $change): string => $change['label_key'])
      ->values();

    return [
      'type' => $changes->isEmpty() ? 'unchanged' : 'changed',
      'categories' => $changes->take(3)->all(),
      'extra' => max(0, $changes->count() - 3),
    ];
  }

  private function comparison(array $current, array $selected): array
  {
    return [
      ['label_key' => 'title_label', 'current' => $this->display(Arr::get($current, 'page.title')), 'selected' => $this->display(Arr::get($selected, 'page.title'))],
      ['label_key' => 'slug_label', 'current' => $this->display(Arr::get($current, 'page.slug')), 'selected' => $this->display(Arr::get($selected, 'page.slug'))],
      ['label_key' => 'workflow_label', 'current' => $this->display(Arr::get($current, 'page.status')), 'selected' => $this->display(Arr::get($selected, 'page.status'))],
      ['label_key' => 'layout_label', 'current' => $this->display(Arr::get($current, 'page.layout_id'), '—'), 'selected' => $this->display(Arr::get($selected, 'page.layout_id'), '—')],
      ['label_key' => 'translations_label', 'current' => count(Arr::get($current, 'translations', [])), 'selected' => count(Arr::get($selected, 'translations', []))],
      ['label_key' => 'slots_label', 'current' => count(Arr::get($current, 'slots', [])), 'selected' => count(Arr::get($selected, 'slots', []))],
      ['label_key' => 'blocks_label', 'current' => count(Arr::get($current, 'blocks', [])), 'selected' => count(Arr::get($selected, 'blocks', []))],
      ['label_key' => 'assets_label', 'current' => count(Arr::get($current, 'page_assets', [])), 'selected' => count(Arr::get($selected, 'page_assets', []))],
    ];
  }

  private function changes(array $from, array $to): array
  {
    $categories = [
      'page' => 'category_page',
      'translations' => 'category_translations',
      'slots' => 'category_slots',
      'blocks' => 'category_blocks',
      'page_assets' => 'category_assets',
    ];

    return collect($categories)->map(function (string $label, string $key) use ($from, $to): array {
      $before = Arr::get($from, $key, []);
      $after = Arr::get($to, $key, []);

      return [
        'key' => $key,
        'label_key' => $label,
        'changed' => $this->canonical($before) !== $this->canonical($after),
        'before_count' => is_array($before) && array_is_list($before) ? count($before) : null,
        'after_count' => is_array($after) && array_is_list($after) ? count($after) : null,
      ];
    })->values()->all();
  }

  private function health(array $snapshot): array
  {
    $issues = [];

    if ((int) Arr::get($snapshot, 'schema_version', 0) !== 1) {
      $issues[] = ['level' => 'blocking', 'message_key' => 'unsupported_schema', 'replace' => []];
    }

    $checks = [
      ['wbcms_page_layouts', array_filter([(int) Arr::get($snapshot, 'page.layout_id')]), 'reference_page_layout'],
      ['wbcms_locales', collect(Arr::get($snapshot, 'translations', []))->pluck('locale_id')->all(), 'reference_locale'],
      ['wbcms_slot_types', collect(Arr::get($snapshot, 'slots', []))->pluck('slot_type_id')->merge(collect(Arr::get($snapshot, 'blocks', []))->pluck('slot_type_id'))->all(), 'reference_slot_type'],
      ['wbcms_shared_slots', collect(Arr::get($snapshot, 'slots', []))->pluck('shared_slot_id')->filter()->all(), 'reference_shared_slot'],
      ['wbcms_block_types', collect(Arr::get($snapshot, 'blocks', []))->pluck('block_type_id')->filter()->all(), 'reference_block_type'],
      ['wbcms_media', $this->mediaIds($snapshot), 'reference_media'],
    ];

    foreach ($checks as [$table, $ids, $label]) {
      $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
      if ($ids === [] || ! Schema::hasTable($table)) {
        continue;
      }
      $found = DB::table($table)->whereIn('id', $ids)->pluck('id')->map(fn ($id): int => (int) $id)->all();
      $missing = array_values(array_diff($ids, $found));
      if ($missing !== []) {
        $issues[] = ['level' => 'blocking', 'message_key' => 'missing_references', 'replace' => ['type_key' => $label, 'ids' => implode(', ', $missing)]];
      }
    }

    $sharedCount = collect(Arr::get($snapshot, 'slots', []))->whereNotNull('shared_slot_id')->count();
    if ($sharedCount > 0) {
      $issues[] = ['level' => 'warning', 'message_key' => 'shared_slot_warning', 'replace' => ['count' => $sharedCount]];
    }

    return [
      'status' => collect($issues)->contains('level', 'blocking') ? 'blocked' : (collect($issues)->contains('level', 'warning') ? 'warning' : 'ready'),
      'issues' => $issues,
    ];
  }

  private function snapshotSummary(array $snapshot): array
  {
    return [
      'captured_at' => Arr::get($snapshot, 'captured_at'),
      'schema_version' => Arr::get($snapshot, 'schema_version'),
      'translations' => count(Arr::get($snapshot, 'translations', [])),
      'slots' => count(Arr::get($snapshot, 'slots', [])),
      'blocks' => count(Arr::get($snapshot, 'blocks', [])),
      'assets' => count(Arr::get($snapshot, 'page_assets', [])),
    ];
  }

  private function mediaIds(array $snapshot): array
  {
    $translationMedia = collect(Arr::get($snapshot, 'translations', []))->pluck('og_image_media_id');
    $blocks = collect(Arr::get($snapshot, 'blocks', []));
    $blockMedia = $blocks->pluck('media_id')->merge($blocks->flatMap(fn (array $block) => collect($block['block_media'] ?? [])->pluck('media_id')));

    return $translationMedia->merge($blockMedia)->filter()->all();
  }

  private function canonical(mixed $value): string
  {
    return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
  }

  private function display(mixed $value, string $empty = '—'): string|int
  {
    return $value === null || $value === '' ? $empty : (is_scalar($value) ? (string) $value : $empty);
  }
}
