<?php

namespace WebBlocks\Cms\Support\Revisions;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;

/** @internal A bounded read window; it never buffers model writes or changes event order. */
final class RestoredReadBatch
{
  public array $models = [];

  public array $relations = [];

  public array $documentIds = [];

  public bool $documentsKnown = false;

  public int $epoch = 0;

  public readonly mixed $pdo;

  public readonly ?string $database;

  public readonly string $prefix;

  public function __construct(public readonly Connection $connection, public readonly int $depth)
  {
    $this->pdo = $connection->getPdo();
    $this->database = $connection->getDatabaseName();
    $this->prefix = $connection->getTablePrefix();
  }

  public function invalidate(): void
  {
    $this->epoch++;
    $this->models = $this->relations = [];
  }

  public function onQuery(QueryExecuted $event, Closure $ids): void
  {
    if (! str_contains($event->sql, ';') && preg_match('/^\s*(select|pragma|explain|show|describe)\b/i', $event->sql)) {
      return;
    }
    $prefix = preg_quote($this->prefix.'wbcms_', '/');
    if ($event->connection !== $this->connection || str_contains($event->sql, ';')
      || ! preg_match('/^\s*(insert\s+into|update|delete\s+from)\s+["`]?'.$prefix.'([a-z_]+)["`]?\s/i', $event->sql, $match)) {
      $this->invalidate();

      return;
    }
    $table = $match[2];
    $relation = [
      'block_text_translations' => 'textTranslations',
      'block_button_translations' => 'buttonTranslations',
      'block_image_translations' => 'imageTranslations',
      'block_contact_form_translations' => 'contactFormTranslations',
      'block_plugin_translations' => 'pluginTranslations',
    ][$table] ?? null;
    if ($relation !== null) {
      $owners = $ids('block_id');
      foreach ($this->relations as $id => &$relations) {
        if ($owners === null || in_array($id, $owners)) {
          unset($relations[$relation]);
        }
      }

      return;
    }
    if (in_array($table, ['block_media', 'block_gallery_item_translations'], true)) {
      return; // Gallery existence remains a current SQL check, never a cached relation.
    }
    if (in_array($table, ['persistent_documents', 'persistent_nodes', 'persistent_items', 'persistent_metadata', 'import_identity_maps', 'import_copied_files'], true)) {
      // A document write can change any cached text translation's virtual meta.
      foreach ($this->relations as &$relations) {
        unset($relations['textTranslations']);
      }
      if ($table === 'persistent_documents' && preg_match('/^\s*insert\s+into\s+\S+\s*\([^)]+\)\s*values\s*\(/i', $event->sql)
        && ! preg_match('/\b(?:block_id|id)\b/i', $event->sql)) {
        return;
      }
      $documents = $ids($table === 'persistent_documents' ? 'id' : 'document_id');
      if ($this->documentsKnown && $documents !== null && array_intersect($documents, $this->documentIds) === []
        && ($table !== 'persistent_documents' || ! preg_match('/\bblock_id\b/i', $event->sql))) {
        return;
      }
    }
    $this->invalidate();
  }
}
