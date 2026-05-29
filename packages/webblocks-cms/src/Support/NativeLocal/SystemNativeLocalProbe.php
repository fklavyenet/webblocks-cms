<?php

namespace WebBlocks\Cms\Support\NativeLocal;

use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class SystemNativeLocalProbe implements NativeLocalProbe
{
  private ?string $databaseFailureMessage = null;

  public function phpVersion(): string
  {
    return PHP_VERSION;
  }

  public function loadedExtensions(): array
  {
    return get_loaded_extensions();
  }

  public function binaryPath(string $binary): ?string
  {
    $path = (new ExecutableFinder)->find($binary);

    return is_string($path) && $path !== '' ? $path : null;
  }

  public function databaseAccessible(): bool
  {
    $this->databaseFailureMessage = null;

    try {
      DB::connection()->getPdo()->query('select 1');

      return true;
    } catch (Throwable $exception) {
      $this->databaseFailureMessage = $exception->getMessage();

      return false;
    }
  }

  public function databaseFailureMessage(): ?string
  {
    return $this->databaseFailureMessage;
  }

  public function redisAccessible(string $host, int $port): bool
  {
    $socket = @fsockopen($host, $port, $errorCode, $errorMessage, 0.5);

    if (! is_resource($socket)) {
      return false;
    }

    fclose($socket);

    return true;
  }

  public function hostsFileContains(string $host): bool
  {
    $contents = @file_get_contents('/etc/hosts');

    if (! is_string($contents)) {
      return false;
    }

    foreach (preg_split('/\R/', $contents) ?: [] as $line) {
      $line = trim(preg_replace('/#.*/', '', $line) ?? '');

      if ($line === '') {
        continue;
      }

      $parts = preg_split('/\s+/', $line) ?: [];

      if (in_array($host, array_slice($parts, 1), true)) {
        return true;
      }
    }

    return false;
  }

  public function fileExists(string $path): bool
  {
    return file_exists($path);
  }

  public function isWritable(string $path): bool
  {
    return is_writable($path);
  }

  public function httpsStatusCode(string $url): ?int
  {
    $curl = $this->binaryPath('curl');

    if ($curl === null) {
      return null;
    }

    try {
      $process = new Process([$curl, '-sS', '-o', '/dev/null', '-w', '%{http_code}', '--max-time', '10', $url]);
      $process->run();

      if (! $process->isSuccessful()) {
        return null;
      }

      $statusCode = trim($process->getOutput());

      return ctype_digit($statusCode) ? (int) $statusCode : null;
    } catch (Throwable) {
      return null;
    }
  }
}
