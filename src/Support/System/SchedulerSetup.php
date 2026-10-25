<?php

namespace WebBlocks\Cms\Support\System;

use Illuminate\Support\Facades\Cache;
use WebBlocks\Cms\Support\SiteNotifications\SchedulerHealth;

/** Configure only the invoking application account, never root or another user. */
class SchedulerSetup
{
  private ?string $prepared = null;

  private ?string $preparedCommand = null;

  public function __construct(private readonly SchedulerHost $host, private readonly SchedulerHealth $health) {}

  public function instructions(): array
  {
    $context = $this->host->context();
    $base = $context['base'];
    $php = $context['php'];
    $valid = is_string($php) && ! preg_match('/[\r\n\x00]/', $base.$php);
    $command = $valid ? 'cd '.escapeshellarg($base).' && '.escapeshellarg($php).' artisan schedule:run >> /dev/null 2>&1' : null;

    return [
      'health' => $this->health->snapshot(), 'timezone' => config('app.timezone'),
      'application' => $base, 'php' => $php, 'schedule' => '* * * * *',
      // Cron treats percent as a newline even inside shell quotes.
      'command' => $command === null ? null : str_replace('%', '\\%', $command),
      'setup_command' => $valid ? 'cd '.escapeshellarg($base).' && '.escapeshellarg($php).' artisan webblocks:scheduler:setup' : null,
      'install_command' => $valid ? 'cd '.escapeshellarg($base).' && '.escapeshellarg($php).' artisan webblocks:scheduler:setup --install' : null,
      'status_command' => $valid ? 'cd '.escapeshellarg($base).' && '.escapeshellarg($php).' artisan webblocks:scheduler:status --json' : null,
    ];
  }

  public function plan(): array
  {
    $this->prepared = null;
    $this->preparedCommand = null;
    $instructions = $this->instructions();
    $context = $this->host->context();
    $status = match (true) {
      $instructions['health']['scheduler_status'] === 'healthy' => 'running',
      ! $context['cli'], ! $context['supported'], $instructions['command'] === null => 'unsupported',
      $context['uid'] === 0 => 'root',
      $context['uid'] !== $context['owner'], ! $context['readable'], ! $context['writable'] => 'account',
      $this->host->externalDefinition($context['base']) => 'external',
      default => 'inspect',
    };
    if ($status !== 'inspect') {
      return ['status' => $status, 'instructions' => $instructions];
    }
    $read = $this->read();
    if ($read === null) {
      return ['status' => 'unavailable', 'instructions' => $instructions];
    }
    $status = $this->classify($read, $instructions);
    if ($status === 'ready') {
      $this->prepared = $read;
      $this->preparedCommand = $instructions['command'];
    }

    return ['status' => $status, 'instructions' => $instructions];
  }

  public function install(): array
  {
    if ($this->prepared === null) {
      return $this->plan();
    }
    $lock = Cache::lock('cms:scheduler-setup', 60);
    if (! $lock->get()) {
      return ['status' => 'busy', 'instructions' => $this->instructions()];
    }
    $attempted = false;
    try {
      $instructions = $this->instructions();
      if ($instructions['health']['scheduler_status'] === 'healthy') {
        return ['status' => 'running', 'instructions' => $instructions];
      }
      if ($this->host->externalDefinition($instructions['application'])) {
        return ['status' => 'external', 'instructions' => $instructions];
      }
      $current = $this->read();
      if ($current === null || ! hash_equals($this->prepared, $current) || $this->preparedCommand !== $instructions['command']) {
        return ['status' => 'changed', 'instructions' => $instructions];
      }
      $context = $this->host->context();
      if (! $context['cli'] || ! $context['supported'] || $context['uid'] === 0 || $context['uid'] !== $context['owner']
        || ! $context['readable'] || ! $context['writable'] || $instructions['command'] === null) {
        return ['status' => 'account', 'instructions' => $instructions];
      }
      $block = $this->marker($instructions['application'])."\n".$instructions['schedule'].' '.$instructions['command']."\n";
      $next = $current.($current !== '' && ! str_ends_with($current, "\n") ? "\n" : '').$block;
      $attempted = true;
      $write = $this->host->crontab($next);
      if ($write['code'] !== 0) {
        return ['status' => 'write_failed', 'instructions' => $instructions];
      }
      $verified = $this->read();

      return ['status' => $verified === $next ? 'installed_unverified' : 'write_unverified', 'instructions' => $instructions];
    } catch (\Throwable) {
      return ['status' => $attempted ? 'write_unverified' : 'unavailable', 'instructions' => $instructions ?? []];
    } finally {
      $this->prepared = null;
      try {
        $lock->release();
      } catch (\Throwable) {
        // The bounded lease expires. A release error must not misreport a completed OS write.
      }
    }
  }

  private function read(): ?string
  {
    $result = $this->host->crontab();
    if ($result['code'] === 0 && strlen($result['out']) <= 262144 && ! str_contains($result['out'], "\0")) {
      return $result['out'];
    }
    if ($result['code'] === 1 && preg_match('/^no crontab for [^\r\n]+\s*$/D', trim($result['error']))) {
      return '';
    }

    return null;
  }

  private function classify(string $text, array $instructions): string
  {
    $expected = $instructions['schedule'].' '.$instructions['command'];
    $entries = [];
    $markers = 0;
    foreach (preg_split('/\R/', $text) as $entry) {
      if (preg_match('/^\s*SHELL\s*=\s*(.+)$/', $entry, $shell) && ! in_array(trim($shell[1], " \"'"), ['/bin/sh', '/bin/bash', '/bin/dash'], true)) {
        return 'conflict';
      }
      if (str_contains($entry, $this->marker($instructions['application']))) {
        $markers++;
      }
      if (str_starts_with(ltrim($entry), '#') || (! str_contains($entry, 'schedule:run') && ! str_contains($entry, 'schedule:work'))) {
        continue;
      }
      $entries[] = trim($entry);
    }
    // Refuse ambiguous wrappers, sibling applications and duplicate entries.
    // Never guess an application's identity from a path substring.
    if ($entries !== []) {
      return $entries === [$expected] && $markers <= 1 ? 'existing' : 'conflict';
    }

    return $markers === 0 ? 'ready' : 'conflict';
  }

  private function marker(string $application): string
  {
    return '# WebBlocks CMS scheduler '.substr(hash('sha256', $application), 0, 20);
  }
}
