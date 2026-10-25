<?php

namespace WebBlocks\Cms\Support\System;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/** OS inspection is CLI-only; raw crontab contents never enter a view or log. */
class SchedulerHost
{
  public function context(): array
  {
    $base = base_path();
    $cli = in_array(PHP_SAPI, ['cli', 'phpdbg'], true);
    $candidates = $cli ? [PHP_BINARY] : array_filter([
      config('webblocks-cms.scheduler.php_binary'),
      '/usr/bin/php'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
      PHP_BINDIR.'/php'.PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
      preg_match('/^php[0-9.]*$/D', basename(PHP_BINARY)) ? PHP_BINARY : null,
      PHP_BINDIR.'/php',
    ]);
    $php = null;
    foreach ($candidates as $candidate) {
      if (is_string($candidate) && ! preg_match('/[\r\n\x00]/', $candidate) && is_file($candidate) && is_executable($candidate)) {
        $php = $candidate;
        break;
      }
    }
    $uid = function_exists('posix_geteuid') ? posix_geteuid() : null;

    return [
      'base' => $base, 'php' => $php, 'cli' => $cli,
      'supported' => PHP_OS_FAMILY !== 'Windows' && $uid !== null,
      'uid' => $uid, 'owner' => is_file($base.'/artisan') ? fileowner($base.'/artisan') : null,
      'readable' => is_readable($base.'/artisan'),
      'writable' => is_writable(storage_path()) && is_writable(base_path('bootstrap/cache')),
    ];
  }

  public function crontab(?string $input = null): array
  {
    $binary = (new ExecutableFinder)->find('crontab');
    if ($binary === null || ! function_exists('proc_open')) {
      return ['code' => 127, 'out' => '', 'error' => 'unavailable'];
    }
    try {
      $process = new Process([$binary, $input === null ? '-l' : '-'], null, ['LC_ALL' => 'C']);
      $process->setTimeout(10);
      if ($input !== null) {
        $process->setInput($input);
      }
      $process->run();

      return ['code' => $process->getExitCode(), 'out' => $process->getOutput(), 'error' => $process->getErrorOutput()];
    } catch (\Throwable) {
      return ['code' => 127, 'out' => '', 'error' => 'unavailable'];
    }
  }

  public function externalDefinition(string $base): bool
  {
    foreach ($this->externalFiles() as $file) {
      if (! is_file($file) || ! is_readable($file) || @filesize($file) > 262144) {
        continue;
      }
      $text = implode("\n", array_filter(@file($file, FILE_IGNORE_NEW_LINES) ?: [], fn ($line) => ! str_starts_with(ltrim($line), '#')));
      if (str_contains($text, $base) && (str_contains($text, 'schedule:run') || str_contains($text, 'schedule:work'))) {
        return true;
      }
    }

    return false;
  }

  protected function externalFiles(): array
  {
    $files = ['/etc/crontab'];
    foreach (['/etc/cron.d/*', '/etc/systemd/system/*.service', '/etc/systemd/system/*.timer', '/usr/lib/systemd/system/*.service'] as $pattern) {
      $files = [...$files, ...(glob($pattern) ?: [])];
    }

    return $files;
  }
}
