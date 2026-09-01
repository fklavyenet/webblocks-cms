<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

namespace WebBlocks\Cms\Support\Updates\Client\Updates;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Runs shell/artisan subprocesses with a timeout and CLI-safe PHP binary
 * resolution. The timeout is configured through
 * `publisher-client.commands.timeout_seconds`.
 */
class UpdateCommandRunner
{
  public function __construct(private readonly ?RunHeartbeat $heartbeat = null)
  {
  }

  public function run(array $command, string $workingDirectory, array &$output): void
  {
    $output[] = '$ '.$this->formatCommand($command);

    // Long subprocesses (composer install, migrations) are exactly where a
    // run spends minutes without touching the pipeline — beat on each output
    // chunk so the lock heartbeat stays fresh while they stream.
    $result = Process::path($workingDirectory)
      ->timeout((int) config('publisher-client.commands.timeout_seconds', 600))
      ->run($command, function (string $type, string $buffer): void {
        $this->heartbeat?->beat();
      });

    $this->appendProcessOutput($result, $output);

    if (! $result->successful()) {
      throw new UpdateException(
        'The update command sequence failed. Review the latest update log for details.',
        'Command failed: '.$this->formatCommand($command),
      );
    }
  }

  public function artisanCommand(array $arguments): array
  {
    return [
      $this->phpBinary(),
      'artisan',
      ...$arguments,
    ];
  }

  /**
   * Build a composer argv that survives a cleared PHP-FPM environment. With no
   * PATH, glibc's posix_spawn fallback searches only /bin:/usr/bin — a bare
   * "composer" living in /usr/local/bin is simply not found. An explicitly
   * configured binary is executed as-is
   * (the operator may point at a wrapper); otherwise composer is located via
   * ExecutableFinder (plus common install dirs) and run THROUGH the resolved
   * PHP binary, sidestepping the phar's `#!/usr/bin/env php` shebang entirely.
   */
  public function composerCommand(array $arguments): array
  {
    $configured = trim((string) config('publisher-client.commands.composer_binary', 'composer'));

    if ($configured !== '' && $configured !== 'composer') {
      return [$configured, ...$arguments];
    }

    $found = (new ExecutableFinder)->find('composer', null, [
      '/usr/local/bin', '/usr/bin', '/opt/homebrew/bin',
    ]);

    if (is_string($found) && $found !== '') {
      return [$this->phpBinary(), $found, ...$arguments];
    }

    return ['composer', ...$arguments];
  }

  public function phpBinary(): string
  {
    $resolvedBinary = $this->resolveCliPhpBinary(PHP_BINARY);

    if ($resolvedBinary !== null) {
      return $resolvedBinary;
    }

    $fallbackBinary = (new ExecutableFinder)->find('php');

    if (is_string($fallbackBinary) && $fallbackBinary !== '') {
      return $fallbackBinary;
    }

    return 'php';
  }

  public function resolveCliPhpBinary(?string $binary): ?string
  {
    if (! is_string($binary)) {
      return null;
    }

    $binary = trim($binary);

    if ($binary === '') {
      return null;
    }

    $normalizedBinary = str_replace('\\', '/', strtolower($binary));
    $basename = basename($normalizedBinary);

    if (str_contains($normalizedBinary, 'php-fpm') || str_starts_with($basename, 'php-fpm')) {
      return null;
    }

    return $binary;
  }

  private function appendProcessOutput(ProcessResult $result, array &$output): void
  {
    $stdout = trim($result->output());
    $stderr = trim($result->errorOutput());

    if ($stdout !== '') {
      $output[] = $stdout;
    }

    if ($stderr !== '') {
      $output[] = $stderr;
    }
  }

  private function formatCommand(array $command): string
  {
    return implode(' ', array_map(static function (string $part): string {
      if ($part === '' || preg_match('/[^A-Za-z0-9_:\/.=-]/', $part) === 1) {
        return "'".str_replace("'", "'\\''", $part)."'";
      }

      return $part;
    }, $command));
  }
}
