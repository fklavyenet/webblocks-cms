<?php

namespace WebBlocks\Cms\Support\Seo;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use WeakMap;
use WebBlocks\Cms\Support\Pages\PublicMount;
use WebBlocks\Cms\Support\WebBlocks;

/** Conservative connection-wide generation: unrelated site edits may also stale a run. */
final class SeoSourceVersion
{
  private WeakMap $schemas;

  public function __construct()
  {
    $this->schemas = new WeakMap;
  }

  public function onQuery(QueryExecuted $event): void
  {
    if (preg_match('/^\s*(select|pragma|explain|show|describe)\b/i', $event->sql)) {
      return;
    }
    if (str_contains($event->sql, 'wbcms_seo_source_versions')) {
      if (preg_match('/\b(create|drop|alter)\b/i', $event->sql)) {
        unset($this->schemas[$event->connection]);
      }

      return;
    }
    $prefix = preg_quote($event->connection->getTablePrefix().'wbcms_', '/');
    if (! preg_match('/\b'.$prefix.'(?:pages|page_translations|page_slots|page_assets|page_access_rules|page_runtime_\w+|page_layout\w*|page_types|blocks|block_\w+|shared_slot\w*|navigation_\w+|sites|site_domains|site_locales|locales|media|media_\w+|assets|slot_types|layouts|layout_types|setting_\w+|persistent_\w+|automatic_page_\w+|embedded_applications|application_\w+|content_source\w*|plugin_\w+|system_settings|consent_\w+)\b/i', $event->sql)) {
      return;
    }
    if (preg_match('/\b'.$prefix.'persistent_\w+\b/i', $event->sql) && ! $this->contentDocumentWrite($event)) {
      return;
    }
    if ($this->schemas[$event->connection] ??= $event->connection->getSchemaBuilder()->hasTable('wbcms_seo_source_versions')) {
      $event->connection->table('wbcms_seo_source_versions')->where('id', 1)->update(['generation' => (string) Str::uuid()]);
    }
  }

  /** Shared typed-document tables also hold token usage writes, which are not content. */
  private function contentDocumentWrite(QueryExecuted $event): bool
  {
    $sql = $event->sql;
    $owners = ['page_id', 'page_slot_id', 'page_layout_id', 'block_id', 'block_text_translation_id'];
    if (preg_match('/\binsert\b/i', $sql) && str_contains($sql, 'wbcms_persistent_documents')) {
      return (bool) preg_match('/\b('.implode('|', $owners).')\b/', $sql);
    }
    $ids = [];
    $column = str_contains($sql, 'wbcms_persistent_documents') ? 'id' : 'document_id';
    if (preg_match('/\bwhere\s+["`]?'.$column.'["`]?\s*=\s*\?/i', $sql, $match, PREG_OFFSET_CAPTURE)) {
      $index = substr_count(substr($sql, 0, $match[0][1]), '?');
      $ids[] = $event->bindings[$index] ?? null;
    } elseif (preg_match('/^\s*insert\s+into\s+\S+\s*\(([^)]+)\)\s*values\s*\(/i', $sql, $match)) {
      $columns = array_map(fn ($name) => trim($name, " \t\n\r\0\x0B\"`"), explode(',', $match[1]));
      $index = array_search('document_id', $columns, true);
      if ($index !== false && count($event->bindings) % count($columns) === 0) {
        foreach (array_chunk($event->bindings, count($columns)) as $row) {
          $ids[] = $row[$index];
        }
      }
    }
    $ids = array_unique(array_filter($ids, fn ($id) => is_int($id) || (is_string($id) && ctype_digit($id))));
    if ($ids === []) {
      return true; // Unrecognized raw/bulk SQL invalidates conservatively.
    }
    $documents = $event->connection->table('wbcms_persistent_documents')->whereIn('id', $ids)->get($owners);
    if ($documents->count() !== count($ids)) {
      return true;
    }

    return $documents->contains(fn ($document) => collect((array) $document)->contains(fn ($value) => $value !== null));
  }

  public function current(): string
  {
    return hash('sha256', serialize([
      DB::table('wbcms_seo_source_versions')->useWritePdo()->where('id', 1)->value('generation'),
      WebBlocks::version(), app(PublicMount::class)->prefix(), config('app.url'),
    ]));
  }
}
