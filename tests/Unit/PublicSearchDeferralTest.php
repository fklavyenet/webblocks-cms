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
    $indexer = (string) file_get_contents(
      dirname(__DIR__, 2).'/src/Support/Search/PublicSearchIndexer.php'
    );

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

    // The catch block deletes every media file the import copied. Once the
    // transaction has committed the site is live, so a failing rebuild must not
    // reach that cleanup.
    $this->assertGreaterThan(
      strpos($mapper, 'throw $throwable;'),
      (int) $rebuildStart,
      'The rebuild must sit outside the rollback try, or an index error deletes a live site\'s media.'
    );
  }
}
