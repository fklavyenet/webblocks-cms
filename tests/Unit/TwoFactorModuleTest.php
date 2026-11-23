<?php

namespace WebBlocks\Cms\Tests\Unit;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Support\Auth\Module\TwoFactor;

final class TwoFactorModuleTest extends TestCase
{
  #[Test]
  public function the_embedded_runtime_matches_its_versioned_manifest(): void
  {
    $directory = dirname(__DIR__, 2).'/src/Support/Auth/Module';
    $manifest = json_decode(file_get_contents($directory.'/module.json'), true, flags: JSON_THROW_ON_ERROR);

    $this->assertSame(2, $manifest['schema']);
    $this->assertSame('fklavyenet/webblocks-two-factor', $manifest['source_package']);
    $this->assertSame('v'.TwoFactor::VERSION, $manifest['source_tag']);
    $this->assertMatchesRegularExpression('/^[a-f0-9]{40}$/D', $manifest['source_commit']);
    $this->assertSame('two-factor', $manifest['module']);
    $this->assertSame(TwoFactor::NAME, $manifest['name']);
    $this->assertSame(TwoFactor::VERSION, $manifest['version']);
    $this->assertSame('WebBlocks\\Cms\\Support\\Auth\\Module', $manifest['namespace']);

    $hashes = [];
    foreach (new FilesystemIterator($directory) as $file) {
      $this->assertTrue($file->isFile() && ! $file->isLink(), $file->getFilename());
      if ($file->getFilename() !== 'module.json') {
        $hashes[$file->getFilename()] = hash_file('sha256', $file->getPathname());
      }
    }
    ksort($hashes);
    $this->assertArrayHasKey('LICENSE', $hashes);
    $this->assertSame($hashes, $manifest['files']);
    $this->assertSame(array_keys(array_diff_key($hashes, ['LICENSE' => true])), array_keys($manifest['source_files']));
    $this->assertSame(hash('sha256', json_encode($hashes, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)), $manifest['aggregate_sha256']);
  }
}
