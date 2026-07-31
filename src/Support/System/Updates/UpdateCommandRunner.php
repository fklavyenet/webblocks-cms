<?php

namespace WebBlocks\Cms\Support\System\Updates;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Process\ExecutableFinder;

class UpdateCommandRunner
{
  public function run(array $command, string $workingDirectory, array &$output): void
  {
    $output[] = '$ '.$this->formatCommand($command);

    $result = Process::path($workingDirectory)
      ->timeout((int) config('webblocks-updates.installer.command_timeout_seconds', 600))
      ->run($command);

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

  /**
   * Runs Composer as `php <entry point>` instead of executing its shim
   * directly. Under php-fpm (clear_env=yes), spawned subprocesses can inherit
   * an empty PATH, so the OS can't resolve the `#!/usr/bin/env php` shebang
   * in Composer's own binary and fails with "env: php: No such file or
   * directory". Invoking the resolved PHP binary directly on Composer's entry
   * file bypasses that shebang/env lookup entirely.
   */
  public function composerCommand(array $arguments): array
  {
    $composerEntryPath = $this->composerEntryPath();

    if ($composerEntryPath === null) {
      throw new UpdateException(
        'The update could not locate a Composer executable to finish installing dependencies.',
        'Unable to resolve a Composer entry point (checked the webblocks-updates.installer.composer_binary config, PATH via ExecutableFinder, and common install locations).',
      );
    }

    return [
      $this->phpBinary(),
      $composerEntryPath,
      ...$arguments,
    ];
  }

  public function composerEntryPath(): ?string
  {
    $configured = trim((string) config('webblocks-updates.installer.composer_binary', ''));

    if ($configured !== '' && File::isFile($configured)) {
      return $configured;
    }

    $found = (new ExecutableFinder)->find('composer');

    if (is_string($found) && $found !== '' && File::isFile($found)) {
      return $found;
    }

    foreach ([
      base_path('composer.phar'),
      '/usr/local/bin/composer',
      '/usr/bin/composer',
    ] as $candidate) {
      if (File::isFile($candidate)) {
        return $candidate;
      }
    }

    return null;
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
