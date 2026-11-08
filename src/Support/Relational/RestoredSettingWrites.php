<?php

namespace WebBlocks\Cms\Support\Relational;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;
use WeakMap;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\Revisions\RestoredIndexTargets;

/** Bounded auxiliary writes for proven new restore owners; model saves stay synchronous. */
final class RestoredSettingWrites
{
  private ?Connection $connection = null;

  private mixed $pdo = null;

  private ?string $database = null;

  private string $prefix = '';

  private array $references = [];

  private array $settings = [];

  private int $bytes = 0;

  private int $nodes = 0;

  private bool $flushing = false;

  private ?Throwable $failure = null;

  private ?SnapshotInsertLimits $limits = null;

  private WeakMap $hooks;

  public function __construct()
  {
    $this->hooks = new WeakMap;
  }

  public function during(Closure $write): mixed
  {
    $connection = DB::connection();
    if ($this->connection !== null || $connection->transactionLevel() === 0 || $connection->getEventDispatcher() !== app('events')) {
      return $write();
    }
    $this->connection = $connection;
    $this->pdo = $connection->getPdo();
    $this->database = $connection->getDatabaseName();
    $this->prefix = $connection->getTablePrefix();
    try {
      $this->limits = SnapshotInsertLimits::forConnection($connection);
      if (! isset($this->hooks[$connection])) {
        $connection->beforeExecuting(static function ($sql, $bindings, $connection): void {
          if (app()->resolved(self::class)) {
            app(self::class)->beforeQuery($connection, $sql);
          }
        });
        $connection->beforeStartingTransaction(static function ($connection): void {
          if (app()->resolved(self::class)) {
            app(self::class)->flush($connection);
          }
        });
        $this->hooks[$connection] = true;
      }
      $result = $write();
      $this->flush($connection);
      if ($this->failure !== null) {
        throw $this->failure;
      }

      return $result;
    } finally {
      $this->connection = null;
      $this->pdo = null;
      $this->database = null;
      $this->prefix = '';
      $this->references = $this->settings = [];
      $this->bytes = $this->nodes = 0;
      $this->limits = null;
      $this->failure = null;
    }
  }

  public function reference(Block $block, array $row): bool
  {
    if (! $this->eligible($block)) {
      return false;
    }
    if (isset($this->references[$block->id]) || count($this->references) >= $this->ownerLimit()) {
      $this->flush($this->connection);
      if (! $this->eligible($block)) {
        return false;
      }
    }
    $this->references[$block->id] = $row;

    return true;
  }

  public function settings(Block $block, string $attribute, string $format, int $bytes, array $nodes, array $records): bool
  {
    if ($attribute !== 'settings' || ! $this->eligible($block)) {
      return false;
    }
    if (isset($this->settings[$block->id])) {
      $this->flush($this->connection);

      return false;
    }
    foreach ($records['block_content_collections'] ?? [] as $index => $row) {
      $id = $row['template_block_id'] ?? null;
      $records['block_content_collections'][$index]['resolved_template_id'] = $id === null ? null : DB::table('wbcms_blocks')->where('id', $id)->value('id');
    }
    if (! $this->eligible($block)) {
      return false;
    }
    $this->settings[$block->id] = [$attribute, $format, $bytes, $nodes, $records];
    $this->bytes += $bytes;
    $this->nodes += count($nodes);
    if (count($this->settings) >= $this->ownerLimit() || $this->bytes >= 1048576 || $this->nodes >= 5000) {
      $this->flush($this->connection);
    }

    return true;
  }

  private function ownerLimit(): int
  {
    return max(1, min(50, $this->limits->parameters - 1));
  }

  private function eligible(Block $block): bool
  {
    if ($this->connection === null || $this->limits === null || $this->flushing || $block->getConnection() !== $this->connection) {
      return false;
    }
    $this->assertConnection();

    return app(RestoredIndexTargets::class)->provesCreation($block);
  }

  public function beforeQuery(Connection $connection, string $sql): void
  {
    if ($connection !== $this->connection || $this->flushing || ($this->references === [] && $this->settings === [])) {
      return;
    }
    $prefix = preg_quote($this->prefix.'wbcms_', '/');
    if (! str_contains($sql, ';') && preg_match('/^\s*(select|pragma|explain|show|describe)\b/i', $sql)
      && ! preg_match('/\b'.$prefix.'(?:setting_\w+|block_(?:application_\w+|appearance_settings|media_settings|navigation_settings|content_\w+))\b/i', $sql)
      && $this->knownRead($sql)) {
      return;
    }
    if (! str_contains($sql, ';') && preg_match('/^\s*insert\s+into\s+["`]?'.$prefix.'(?:blocks|persistent_\w+|import_identity_maps|import_copied_files)["`]?\s*\(/i', $sql)
      && ! preg_match('/\b(conflict|duplicate|replace|select)\b/i', $sql)) {
      return;
    }
    $this->flush($connection);
  }

  private function knownRead(string $sql): bool
  {
    // An unfamiliar view/function can read settings without naming their tables.
    if (preg_match_all('/\b([a-z_]+)\s*\(/i', $sql, $calls)) {
      foreach ($calls[1] as $call) {
        if (! in_array(strtolower($call), ['count', 'exists', 'coalesce', 'length', 'cast', 'lower', 'upper', 'sum', 'min', 'max', 'avg', 'in', 'not', 'and', 'or', 'where', 'schema', 'database', 'pragma_table_xinfo', 'pragma_table_info'], true)) {
          return false;
        }
      }
    }
    if (! preg_match_all('/\b(?:from|join)\s+((?:["`\w]+\.)?["`]?\w+["`]?)/i', $sql, $matches)) {
      return false;
    }
    static $safe;
    $safe ??= array_fill_keys([
      'blocks', 'block_types', 'block_text_translations', 'block_button_translations', 'block_image_translations',
      'block_contact_form_translations', 'block_plugin_translations', 'block_gallery_item_translations', 'block_media',
      'slot_types', 'pages', 'page_translations', 'page_runtime_settings', 'page_runtime_items', 'page_slots', 'page_assets',
      'page_access_rules', 'page_layouts', 'page_layout_slots', 'page_types', 'page_revisions', 'page_revision_candidates',
      'page_list_cache_versions', 'sites', 'site_locales', 'site_domains', 'site_notification_policies', 'locales',
      'system_settings', 'embedded_applications', 'persistent_documents', 'persistent_nodes', 'navigation_cache_versions',
      'navigation_menus', 'navigation_items', 'seo_source_versions', ...array_keys(PersistentDocuments::tables()),
    ], true);
    $prefix = $this->prefix.'wbcms_';
    foreach ($matches[1] as $name) {
      $parts = explode('.', str_replace(['"', '`'], '', $name));
      $table = end($parts);
      if (count($parts) === 2 && $parts[0] === 'information_schema' && in_array($table, ['tables', 'columns'], true)) {
        continue;
      }
      if (! in_array($table, ['sqlite_master', 'sqlite_schema', 'pragma_table_xinfo', 'pragma_table_info'], true)
        && (! str_starts_with($table, $prefix) || ! isset($safe[substr($table, strlen($prefix))]))) {
        return false;
      }
    }

    return true;
  }

  public function rollback(Connection $connection): void
  {
    if ($connection === $this->connection) {
      $this->references = $this->settings = [];
      $this->bytes = $this->nodes = 0;
    }
  }

  private function assertConnection(): void
  {
    if ($this->failure !== null) {
      throw $this->failure;
    }
    $connection = $this->connection;
    if ($connection === null || DB::connection() !== $connection || $connection->transactionLevel() === 0
      || $connection->getEventDispatcher() !== app('events') || $connection->getPdo() !== $this->pdo
      || $connection->getDatabaseName() !== $this->database || $connection->getTablePrefix() !== $this->prefix) {
      throw new LogicException('The settings restoration transaction changed connection identity.');
    }
  }

  public function flush(Connection $connection): void
  {
    if ($connection !== $this->connection || $this->flushing || ($this->references === [] && $this->settings === [])) {
      return;
    }
    $this->assertConnection();
    $this->flushing = true;
    try {
      $references = $this->references;
      $settings = $this->settings;
      $this->references = $this->settings = [];
      $this->bytes = $this->nodes = 0;
      if ($references !== []) {
        $table = 'wbcms_block_application_references';
        $existing = $connection->table($table)->whereIn('block_id', array_keys($references))->exists();
        if ($existing) {
          foreach ($references as $row) {
            $connection->table($table)->upsert([$row], ['block_id'], array_values(array_diff(array_keys($row), ['block_id'])));
          }
        } else {
          $this->insert($table, array_values($references));
        }
      }
      if ($settings === []) {
        return;
      }
      $headers = [];
      foreach ($settings as $block => [$attribute, $format, $bytes, $nodes]) {
        $headers[] = ['block_id' => $block, 'attribute' => $attribute, 'format' => $format, 'payload_bytes' => $bytes, 'node_count' => count($nodes)];
      }
      $this->insert('wbcms_setting_documents', $headers);
      $ids = $connection->table('wbcms_setting_documents')->whereIn('block_id', array_keys($settings))->where('attribute', 'settings')->pluck('id', 'block_id');
      $nodeRows = $typed = [];
      foreach ($settings as $block => [, , , $nodes, $records]) {
        $id = $ids[$block];
        foreach ($nodes as $node) {
          $nodeRows[] = ['document_id' => $id, ...$node];
        }
        foreach ($records as $table => $rows) {
          foreach ($rows as $row) {
            $typed[$table][] = ['document_id' => $id, ...$row];
          }
        }
      }
      foreach (array_keys(SettingDefinitions::tables()) as $table) {
        if (isset($typed[$table])) {
          $this->insert('wbcms_'.$table, $typed[$table]);
        }
      }
      $this->insert('wbcms_setting_nodes', $nodeRows);
    } catch (Throwable $exception) {
      $this->failure ??= $exception;
      throw $exception;
    } finally {
      $this->flushing = false;
    }
  }

  private function insert(string $table, array $rows): void
  {
    if ($rows === []) {
      return;
    }
    $columns = [];
    foreach ($rows as $row) {
      $columns += array_fill_keys(array_keys($row), null);
    }
    $batch = new SnapshotInsertBatch($this->connection, $table, $columns, $this->limits);
    foreach ($rows as $row) {
      $batch->add($row);
    }
    $batch->finish();
  }
}
