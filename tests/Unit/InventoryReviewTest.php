<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use WebBlocks\Cms\Tests\Support\InventoryReview;

class InventoryReviewTest extends TestCase
{
  private string $fixture;

  protected function setUp(): void
  {
    parent::setUp();
    $this->fixture = sys_get_temp_dir().'/cms-inventory-review-'.bin2hex(random_bytes(8));
    foreach (InventoryReview::SOURCE_ROOTS as $directory) {
      mkdir($this->fixture.'/'.$directory, 0775, true);
    }
    mkdir($this->fixture.'/resources/contracts', 0775, true);
    mkdir($this->fixture.'/src/Support', 0775, true);
    file_put_contents($this->fixture.'/src/Support/WebBlocks.php', "<?php const VERSION = '99.1.0';\n");
    file_put_contents($this->fixture.'/composer.json', '{}');
    copy(dirname(__DIR__, 2).'/'.InventoryReview::DOCUMENT, $this->fixture.'/'.InventoryReview::DOCUMENT);
    $this->save(InventoryReview::record($this->fixture, 'Synthetic baseline review.'));
  }

  protected function tearDown(): void
  {
    $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->fixture, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
      $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($this->fixture);
    parent::tearDown();
  }

  public function test_current_product_review_matches_real_model_helpers(): void
  {
    $root = dirname(__DIR__, 2);
    $this->assertSame([], InventoryReview::errors($root));
    $this->assertSame(InventoryReview::surface(), InventoryReview::read($root)['core_surface']);
  }

  public function test_added_modified_and_deleted_runtime_files_invalidate_review(): void
  {
    $path = $this->fixture.'/src/NewAuthoringField.php';
    file_put_contents($path, '<?php // new field');
    $this->assertNotEmpty(InventoryReview::errors($this->fixture));
    $this->save(InventoryReview::record($this->fixture, 'Reviewed addition.', 'Synthetic source with no product behavior.'));
    file_put_contents($path, '<?php // changed enum');
    $this->assertNotEmpty(InventoryReview::errors($this->fixture));
    $this->save(InventoryReview::record($this->fixture, 'Reviewed change.', 'Synthetic source with no product behavior.'));
    unlink($path);
    $this->assertNotEmpty(InventoryReview::errors($this->fixture));
  }

  public function test_prose_changes_and_release_version_changes_invalidate_review(): void
  {
    file_put_contents($this->fixture.'/'.InventoryReview::DOCUMENT, "\nUnreviewed prose.\n", FILE_APPEND);
    $this->assertStringContainsString('prose changed', implode(' ', InventoryReview::errors($this->fixture)));
    $this->save(InventoryReview::record($this->fixture, 'Reviewed prose.'));
    file_put_contents($this->fixture.'/src/Support/WebBlocks.php', "<?php const VERSION = '99.1.1';\n");
    $this->assertStringContainsString('different product version', implode(' ', InventoryReview::errors($this->fixture)));
  }

  public function test_unchanged_prose_needs_a_deliberate_no_impact_explanation(): void
  {
    file_put_contents($this->fixture.'/src/Changed.php', '<?php // change');
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Runtime changed but inventory prose did not');
    InventoryReview::record($this->fixture, 'Only updating a checksum is insufficient.');
  }

  public function test_missing_block_and_incorrect_catalog_count_cannot_be_blessed(): void
  {
    $document = file_get_contents($this->fixture.'/'.InventoryReview::DOCUMENT);
    $surface = InventoryReview::surface();
    $badCount = preg_replace('/current published core catalog contains \d+ rows/', 'current published core catalog contains 999 rows', $document);
    $this->assertNotEmpty(InventoryReview::documentErrors($badCount, $surface));
    $missing = str_replace('### `header`', '### `removed-header`', $document);
    $this->assertStringContainsString('exactly once: header', implode(' ', InventoryReview::documentErrors($missing, $surface)));
    file_put_contents($this->fixture.'/'.InventoryReview::DOCUMENT, $missing);
    $this->expectException(RuntimeException::class);
    InventoryReview::record($this->fixture, 'A review flag must not waive structural checks.');
  }

  public function test_hidden_files_and_editor_backups_do_not_change_the_source_set(): void
  {
    $before = InventoryReview::sources($this->fixture);
    file_put_contents($this->fixture.'/src/.DS_Store', 'synthetic');
    file_put_contents($this->fixture.'/src/Example.php.bak', 'synthetic');
    file_put_contents($this->fixture.'/src/Example.php~', 'synthetic');
    $this->assertSame($before, InventoryReview::sources($this->fixture));
  }

  public function test_symlinks_cannot_hide_changed_runtime_sources(): void
  {
    symlink($this->fixture.'/composer.json', $this->fixture.'/src/Linked.php');
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('must not contain symlinks');
    InventoryReview::sources($this->fixture);
  }

  public function test_review_notes_reject_credential_shaped_values(): void
  {
    $this->expectException(RuntimeException::class);
    InventoryReview::record($this->fixture, 'token=synthetic-credential');
  }

  public function test_relative_user_domain_paths_are_not_machine_paths(): void
  {
    $record = InventoryReview::record($this->fixture, 'Reviewed src/Policies/Users/UserPolicy.php.');
    $this->assertSame('Reviewed src/Policies/Users/UserPolicy.php.', $record['review_note']);
  }

  public function test_product_version_is_not_confused_with_ui_version(): void
  {
    file_put_contents($this->fixture.'/src/Support/WebBlocks.php', "<?php const UI_VERSION = '2.30.0'; const string VERSION = '99.1.2';\n");
    $this->assertSame('99.1.2', InventoryReview::version($this->fixture));
  }

  private function save(array $record): void
  {
    file_put_contents($this->fixture.'/'.InventoryReview::RECORD, json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
  }
}
