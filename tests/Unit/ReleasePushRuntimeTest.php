<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

class ReleasePushRuntimeTest extends TestCase
{
  public static function transports(): array
  {
    return ['default SSH' => [false], 'operator transport' => ['fixture-custom-ssh']];
  }

  #[DataProvider('transports')]
  public function test_release_wrapper_keeps_explicit_refs_transport_and_remote_verification(string|false $transport): void
  {
    $directory = sys_get_temp_dir().'/cms-release-fixture-'.bin2hex(random_bytes(6));
    mkdir($directory);
    $git = <<<'GIT'
#!/usr/bin/env php
<?php
$args = array_slice($argv, 1);
$sha = str_repeat('a', 40);
file_put_contents(getenv('CMS_TEST_GIT_LOG'), json_encode(['args' => $args, 'ssh' => getenv('GIT_SSH_COMMAND')])."\n", FILE_APPEND);
switch ($args[0]) {
  case 'symbolic-ref': echo "main\n"; break;
  case 'rev-parse': case 'rev-list': echo $sha."\n"; break;
  case 'ls-remote':
    foreach (array_slice($args, 2) as $ref) {
      echo $sha."\t".$ref."\n";
    }
    break;
}
GIT;
    file_put_contents($directory.'/git', $git);
    chmod($directory.'/git', 0755);
    $log = $directory.'/calls';
    $process = new Process([PHP_BINARY, dirname(__DIR__, 2).'/scripts/release/push.php'], dirname(__DIR__, 2), [
      'PATH' => $directory.PATH_SEPARATOR.getenv('PATH'), 'GIT_SSH_COMMAND' => $transport, 'GIT_SSH' => false,
      'CMS_TEST_GIT_LOG' => $log, 'WEBBLOCKS_RELEASE_PRIMARY_REMOTE' => 'fixture-origin', 'WEBBLOCKS_RELEASE_BACKUP_REMOTE' => 'fixture-backup',
    ], timeout: 10);
    try {
      $process->run();
      $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().$process->getOutput());
      $calls = array_map(fn ($line) => json_decode($line, true), file($log, FILE_IGNORE_NEW_LINES));
      $pushes = array_values(array_filter($calls, fn ($call) => $call['args'][0] === 'push'));
      $this->assertCount(2, $pushes);
      $this->assertSame(['push', 'fixture-origin', 'refs/heads/main:refs/heads/main'], array_slice($pushes[0]['args'], 0, 3));
      $this->assertSame(['push', '--no-verify', 'fixture-backup'], array_slice($pushes[1]['args'], 0, 3));
      $this->assertSame($pushes[0]['args'][3], $pushes[1]['args'][4]);
      $this->assertSame($transport ?: 'ssh -o ServerAliveInterval=15 -o ServerAliveCountMax=4', $pushes[0]['ssh']);
      $this->assertCount(4, array_filter($calls, fn ($call) => $call['args'][0] === 'ls-remote'));
    } finally {
      foreach ([$directory.'/git', $log] as $path) {
        if (is_file($path)) {
          unlink($path);
        }
      }
      rmdir($directory);
    }
  }
}
