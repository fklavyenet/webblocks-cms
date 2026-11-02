<?php

namespace WebBlocks\Cms\Tests\Unit;

use Illuminate\Support\Facades\File;
use WebBlocks\Cms\Support\System\Updates\CmsComposerState;
use WebBlocks\Cms\Support\System\Updates\UpdateException;
use WebBlocks\Cms\Support\WebBlocks;
use WebBlocks\Cms\Tests\TestCase;

class CmsComposerStateTest extends TestCase
{
  public function test_consistent_current_composer_records_allow_completion(): void
  {
    $this->withFixture(false, function (): void {
      app(CmsComposerState::class)->assertCurrent();
      $this->addToAssertionCount(1);
    });
  }

  public function test_different_source_references_reject_completion_even_when_versions_match(): void
  {
    $this->expectException(UpdateException::class);
    $this->withFixture(true, fn () => app(CmsComposerState::class)->assertCurrent());
  }

  private function withFixture(bool $mismatch, callable $check): void
  {
    $base = base_path();
    $root = sys_get_temp_dir().'/cms-composer-state-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($root.'/vendor/composer');
    $record = ['name' => 'fklavyenet/webblocks-cms', 'version' => 'v'.WebBlocks::version(), 'source' => ['reference' => 'synthetic-current']];
    File::put($root.'/composer.lock', json_encode(['packages' => [$record]], JSON_THROW_ON_ERROR));
    if ($mismatch) {
      $record['source']['reference'] = 'synthetic-old';
    }
    File::put($root.'/vendor/composer/installed.json', json_encode(['packages' => [$record]], JSON_THROW_ON_ERROR));
    app()->setBasePath($root);
    try {
      $check();
    } finally {
      app()->setBasePath($base);
      File::deleteDirectory($root);
    }
  }
}
