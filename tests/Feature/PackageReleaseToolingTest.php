<?php

namespace WebBlocks\Cms\Tests\Feature;

use WebBlocks\Cms\Tests\TestCase;

class PackageReleaseToolingTest extends TestCase
{
  public function test_composer_exposes_package_only_release_commands(): void
  {
    $composer = json_decode((string) file_get_contents(__DIR__.'/../../composer.json'), true, 512, JSON_THROW_ON_ERROR);

    $this->assertSame('scripts/release/prepare.sh', $composer['scripts']['release:prepare'] ?? null);
    $this->assertSame('scripts/release/publish-update.sh', $composer['scripts']['release:publish-update'] ?? null);
  }

  public function test_release_scripts_use_canonical_root_and_testbench_without_legacy_paths(): void
  {
    $prepare = (string) file_get_contents(__DIR__.'/../../scripts/release/prepare.sh');
    $publish = (string) file_get_contents(__DIR__.'/../../scripts/release/publish-update.sh');
    $surface = $prepare."\n".$publish;

    $this->assertStringContainsString('HEAD:src/Support/WebBlocks.php', $prepare);
    $this->assertStringContainsString('git archive --format=tar --worktree-attributes HEAD', $prepare);
    $this->assertStringContainsString('vendor/bin/testbench webblocks:publish-update', $publish);
    $this->assertStringNotContainsString('packages/webblocks-cms', $surface);
    $this->assertStringNotContainsString(' artisan ', $surface);
    $this->assertStringNotContainsString('legacy-harness', $surface);
  }
}
