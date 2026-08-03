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

  /**
   * Tagging is a release-flow step that publishing does not technically need,
   * which is why it was the one that got skipped — 19 published versions
   * between 1.37.1 and 1.48.3 shipped with no tag at all. The artifact is built
   * from HEAD, so prepare is the natural place to require one.
   */
  public function test_prepare_refuses_to_build_an_artifact_for_an_untagged_release(): void
  {
    $prepare = (string) file_get_contents(__DIR__.'/../../scripts/release/prepare.sh');

    $this->assertStringContainsString('TAG_NAME="v${VERSION}"', $prepare);
    $this->assertStringContainsString('git rev-parse -q --verify "refs/tags/${TAG_NAME}"', $prepare);

    // An annotated tag carries the tagger and message the flow asks for; a
    // lightweight one is just a moveable pointer.
    $this->assertStringContainsString('git cat-file -t "refs/tags/${TAG_NAME}"', $prepare);

    // A tag naming some other commit is the failure mode six existing tags
    // already have, and it would ship an artifact the tag does not describe.
    $this->assertStringContainsString('TAG_COMMIT="$(git rev-list -n1 "refs/tags/${TAG_NAME}")"', $prepare);
    $this->assertStringContainsString('HEAD_COMMIT="$(git rev-parse HEAD)"', $prepare);
    $this->assertStringContainsString('if [ "${TAG_COMMIT}" != "${HEAD_COMMIT}" ]; then', $prepare);

    // The guard is worthless if it runs after the artifact is already built.
    $this->assertLessThan(
      (int) strpos($prepare, 'git archive --format=tar'),
      (int) strpos($prepare, 'TAG_NAME="v${VERSION}"'),
      'The tag guard must run before the artifact is assembled.'
    );
  }
}
