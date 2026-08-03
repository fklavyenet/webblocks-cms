<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use WebBlocks\Cms\Support\Search\PublicSearchIndexer;

/**
 * Guards for the bulk-write search deferral.
 *
 * Every block, translation and slot save reindexes the whole page it belongs
 * to. For one editor edit that is correct; for a bulk writer it is quadratic —
 * importing this project's own site (7726 blocks, 4526 text translations over
 * 72 pages) walked every page's block tree once per row and took 7m54s of pure
 * CPU. Deferring the reactive path and rebuilding once brought it to 28s with a
 * byte-identical index.
 */
class PublicSearchDeferralTest extends TestCase
{
  protected function tearDown(): void
  {
    // A leaked deferral would silently stop indexing for the rest of the suite.
    $this->assertFalse(PublicSearchIndexer::isDeferred());
    $this->assertFalse(PublicSearchIndexer::isCoalescing());

    parent::tearDown();
  }

  #[Test]
  public function indexing_is_live_until_a_bulk_writer_defers_it(): void
  {
    $this->assertFalse(PublicSearchIndexer::isDeferred());

    $seen = PublicSearchIndexer::deferring(fn (): bool => PublicSearchIndexer::isDeferred());

    $this->assertTrue($seen);
    $this->assertFalse(PublicSearchIndexer::isDeferred());
  }

  #[Test]
  public function the_callback_return_value_is_passed_through(): void
  {
    $this->assertSame(
      'imported-site',
      PublicSearchIndexer::deferring(fn (): string => 'imported-site')
    );
  }

  #[Test]
  public function nested_deferrals_only_resume_indexing_at_the_outermost_exit(): void
  {
    PublicSearchIndexer::deferring(function (): void {
      PublicSearchIndexer::deferring(function (): void {
        $this->assertTrue(PublicSearchIndexer::isDeferred());
      });

      // The inner block finished, but the outer bulk write has not.
      $this->assertTrue(PublicSearchIndexer::isDeferred());
    });

    $this->assertFalse(PublicSearchIndexer::isDeferred());
  }

  #[Test]
  public function a_failed_bulk_write_resumes_indexing(): void
  {
    try {
      PublicSearchIndexer::deferring(function (): void {
        throw new RuntimeException('Import package is missing media file.');
      });
      $this->fail('Expected the callback exception to propagate.');
    } catch (RuntimeException $exception) {
      $this->assertSame('Import package is missing media file.', $exception->getMessage());
    }

    $this->assertFalse(PublicSearchIndexer::isDeferred());
  }

  #[Test]
  public function only_the_reactive_refresh_path_is_deferrable(): void
  {
    $indexer = $this->indexer();

    foreach (['refreshPage', 'refreshSharedSlot'] as $method) {
      $this->assertMatchesRegularExpression(
        '/public function '.$method.'\([^)]*\): void\s*\{\s*if \(self::\$deferred > 0/',
        $indexer,
        sprintf('%s() is a save-hook entry point and must honour the deferral.', $method)
      );
    }

    // rebuild()/rebuildPage() are what the bulk writer calls when it is done.
    // Gating them too would leave the imported pages out of the index for good.
    foreach (['rebuild', 'rebuildPage'] as $method) {
      $this->assertDoesNotMatchRegularExpression(
        '/public function '.$method.'\([^)]*\): PublicSearchRebuildResult\s*\{\s*[^}]*self::\$deferred/',
        $indexer,
        sprintf('%s() is the explicit path and must always index.', $method)
      );
    }
  }

  #[Test]
  public function a_bulk_deferral_outranks_coalescing(): void
  {
    $indexer = $this->indexer();

    // A deferring bulk writer rebuilds the index itself afterwards. If the
    // coalescing check ran first, its rows would queue up and be rebuilt a
    // second time — exactly the cost the deferral exists to avoid.
    foreach (['refreshPage', 'refreshSharedSlot'] as $method) {
      $body = $this->methodBody($indexer, $method);

      $this->assertLessThan(
        (int) strpos($body, 'self::$coalescing > 0'),
        (int) strpos($body, 'self::$deferred > 0'),
        sprintf('%s() must check the deferral before the coalescing queue.', $method)
      );
    }
  }

  #[Test]
  public function every_admin_write_route_coalesces_its_reindexes(): void
  {
    $routes = (string) file_get_contents(dirname(__DIR__, 2).'/routes/admin.php');

    // The admin group is the block editor; the internal API group is the same
    // writes under a token. A group left out keeps the per-row sweep.
    $this->assertStringContainsString(
      "'admin.access', CoalesceSearchIndexing::class]",
      $routes,
      'The admin group performs the block writes this exists for.'
    );
    $this->assertStringContainsString(
      "'internal-api.token', CoalesceSearchIndexing::class]",
      $routes,
      'The Internal Content API writes the same rows as the admin.'
    );
  }

  #[Test]
  public function the_middleware_opens_a_scope_on_writes_and_closes_it_after_the_response(): void
  {
    $middleware = (string) file_get_contents(
      dirname(__DIR__, 2).'/src/Http/Middleware/CoalesceSearchIndexing.php'
    );

    // A read opening a scope would be harmless but pointless; the cost being
    // collapsed only exists on writes.
    $this->assertStringContainsString('if (! $request->isMethodCacheable()) {', $middleware);
    $this->assertStringContainsString('$this->indexer->beginCoalescing();', $middleware);

    // terminate() runs after the response is sent, so the editor waits for the
    // redirect, not for the rebuild.
    $this->assertMatchesRegularExpression(
      '/public function terminate\([^)]*\): void\s*\{[^}]*endCoalescing\(\)/',
      $middleware,
      'The flush belongs in terminate(), or the editor waits for it anyway.'
    );
  }

  private function indexer(): string
  {
    return (string) file_get_contents(
      dirname(__DIR__, 2).'/src/Support/Search/PublicSearchIndexer.php'
    );
  }

  private function methodBody(string $source, string $method): string
  {
    $start = strpos($source, 'public function '.$method.'(');
    $this->assertNotFalse($start, sprintf('%s() is gone; this guard is describing code that no longer exists.', $method));

    $next = strpos($source, "\n  public function ", (int) $start + 1);

    return $next === false
      ? substr($source, (int) $start)
      : substr($source, (int) $start, $next - (int) $start);
  }

  #[Test]
  public function the_import_defers_and_then_rebuilds_once(): void
  {
    $mapper = (string) file_get_contents(
      dirname(__DIR__, 2).'/src/Support/Sites/ExportImport/ImportDataMapper.php'
    );

    $this->assertStringContainsString('PublicSearchIndexer::deferring(', $mapper);

    $deferStart = strpos($mapper, 'PublicSearchIndexer::deferring(');
    $rebuildStart = strpos($mapper, 'app(PublicSearchIndexer::class)->rebuild(');

    $this->assertNotFalse($rebuildStart, 'A deferred import that never rebuilds leaves an empty index.');
    $this->assertGreaterThan(
      (int) $deferStart,
      (int) $rebuildStart,
      'The rebuild belongs after the deferred transaction, so it reads committed rows.'
    );
    $this->assertSame(
      1,
      substr_count($mapper, 'app(PublicSearchIndexer::class)->rebuild('),
      'One rebuild per import; a second one repeats the whole cost it just saved.'
    );
  }

  #[Test]
  public function a_failed_step_keeps_the_files_it_copied(): void
  {
    $mapper = (string) file_get_contents(
      dirname(__DIR__, 2).'/src/Support/Sites/ExportImport/ImportDataMapper.php'
    );

    // The one-shot import deleted every copied file when it threw, because its
    // single transaction rolled back and the files would have been orphans.
    // A chunked import is resumable, so those files are the work a resume would
    // otherwise repeat — deleting them belongs to the explicit teardown in
    // SiteImportManager::discardImportedSite(), not to a step that may retry.
    $this->assertStringNotContainsString('File::delete(public_path(', $mapper);
    $this->assertStringNotContainsString('Storage::disk($disk)->delete($path)', $mapper);
    $this->assertStringContainsString('$this->saveState($siteImport, $state, $payload, [$failure], SiteImport::STATUS_FAILED', $mapper);
  }
}
