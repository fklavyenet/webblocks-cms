<?php

namespace WebBlocks\Cms\Support\Search;

use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Support\Database\CmsTable;
use WebBlocks\Cms\Support\Revisions\RestoredIndexTargets;

/** Reuse only page targets during an explicit transactional, coalesced copy. */
final class BlockIndexTargets
{
  private int $depth = 0;

  private array $pages = [];

  private int $generation = 0;

  public function during(callable $copy): mixed
  {
    if (DB::connection()->transactionLevel() === 0) {
      return $copy();
    }
    $this->depth++;
    try {
      return app(RestoredIndexTargets::class)->during(fn () => $copy());
    } finally {
      if (--$this->depth === 0) {
        $this->pages = [];
      }
    }
  }

  public function freshForIndex(Block $block): ?Block
  {
    if ($this->depth === 0 || $block::class !== Block::class || ! PublicSearchIndexer::isCoalescing()
      || $block->getConnection()->transactionLevel() === 0 || ! $this->canReusePages()) {
      return $block->fresh('page');
    }
    // Always reread the block: an updating observer may move it using raw SQL.
    $fresh = $block->fresh();
    if ($fresh === null) {
      return null;
    }
    $related = $fresh->page()->getRelated();
    $connection = $related->getConnection();
    $key = serialize([spl_object_id($connection), spl_object_id($connection->getPdo()), $connection->getDatabaseName(), $connection->getTablePrefix(), $related::class, $related->getTable(), $fresh->page_id]);
    if (! array_key_exists($key, $this->pages)) {
      $generation = $this->generation;
      $page = $fresh->page()->first();
      $mutable = $page !== null && $this->containsObject($page->settings);
      if ($generation !== $this->generation || ! $this->canReusePages() || $mutable) {
        return $fresh->setRelation('page', $page);
      }
      $this->pages[$key] = $page;
    }

    $page = $this->pages[$key] === null ? null : clone $this->pages[$key];
    if ($page !== null) {
      $page->setRelation('translations', $page->translations->map(fn ($row) => (clone $row)->unsetRelations()));
    }

    return $fresh->setRelation('page', $page);
  }

  private function canReusePages(): bool
  {
    if (DB::connection()->getEventDispatcher() !== app('events')) {
      return false;
    }
    foreach ([Page::class, PageTranslation::class] as $class) {
      if ($class::getEventDispatcher() !== app('events') || ($class::getAllGlobalScopes()[$class] ?? []) !== []
        || app('events')->hasListeners('eloquent.retrieved: '.$class)) {
        return false;
      }
    }

    return true;
  }

  private function containsObject(mixed $value): bool
  {
    if (is_object($value)) {
      return true;
    }
    foreach (is_array($value) ? $value : [] as $item) {
      if ($this->containsObject($item)) {
        return true;
      }
    }

    return false;
  }

  public function invalidate(?string $sql = null): void
  {
    if ($this->depth === 0 || ($sql !== null && preg_match('/^\s*(select|pragma|explain|show|describe)\b/i', $sql))) {
      return;
    }
    if ($sql === null || stripos($sql, CmsTable::name('pages')) !== false
      || stripos($sql, CmsTable::name('page_translations')) !== false
      || stripos($sql, 'wbcms_page_runtime_') !== false || stripos($sql, 'wbcms_persistent_') !== false
      || stripos($sql, CmsTable::name('shared_slots')) !== false) {
      $this->generation++;
      $this->pages = [];
    }
  }
}
