<?php

namespace WebBlocks\Cms\Support\Search;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\AutomaticPageRule;

final class ExtensionsSearchIndex
{
  public const SOURCE = 'webblocks-extensions-live::plugins';

  public function manages(AutomaticPageRule $rule): bool
  {
    return $rule->base_path === '/extensions' && $rule->source_handle === self::SOURCE
      && $rule->site?->handle === config('cms.extensions_search.site');
  }

  public function reads(AutomaticPageRule $rule): bool
  {
    return config('cms.extensions_search.read_index') && $this->manages($rule);
  }

  public function entries(AutomaticPageRule $rule, string $locale): Collection
  {
    if (! Schema::hasTable('wbcms_extensions_search_entries')) {
      return collect();
    }

    return DB::table('wbcms_extensions_search_entries as entries')
      ->join('wbcms_extensions_search_publications as publications', 'publications.rule_id', '=', 'entries.rule_id')
      ->where('entries.rule_id', $rule->id)
      ->where('publications.revision', $rule->revision)
      ->where('entries.locale', $locale)
      ->get(['entries.record_key', 'entries.title', 'entries.description']);
  }

  public function apply(AutomaticPageRule $rule, array $snapshot): void
  {
    DB::transaction(function () use ($rule, $snapshot): void {
      $current = AutomaticPageRule::query()->lockForUpdate()->findOrFail($rule->id);
      abort_unless($current->status === 'active' && $this->manages($current), 409);

      $publications = DB::table('wbcms_extensions_search_publications');
      $published = (clone $publications)->where('rule_id', $rule->id)->first();
      if ($published && $published->sequence >= $snapshot['sequence']) {
        return;
      }

      $entries = DB::table('wbcms_extensions_search_entries');
      $retained = (int) ($published?->revision ?? 0) === $current->revision ? $snapshot['pending'] : [];
      $obsolete = (clone $entries)->where('rule_id', $rule->id);
      if ($retained !== []) {
        $obsolete->whereNotIn('record_key', $retained);
      }
      $obsolete->delete();

      $publications->updateOrInsert(['rule_id' => $rule->id], [
        'revision' => $current->revision,
        'sequence' => $snapshot['sequence'],
        'received_at' => now(),
      ]);

      $normalizer = app(SearchTextNormalizer::class);
      foreach ($snapshot['records'] as $record) {
        $rows = [];
        foreach ($record['translations'] as $locale => $fields) {
          $rows[] = [
            'rule_id' => $rule->id,
            'record_key' => $record['key'],
            'locale' => $locale,
            'title' => $normalizer->normalize($fields['title']),
            'description' => $normalizer->normalize($fields['description']),
          ];
        }
        $entries->insert($rows);
      }
    });
  }
}
