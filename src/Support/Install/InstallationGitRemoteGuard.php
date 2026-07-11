<?php

namespace WebBlocks\Cms\Support\Install;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class InstallationGitRemoteGuard
{
  public function protectCurrentInstall(?string $repositoryPath = null, array &$output = []): bool
  {
    $repositoryPath ??= base_path();

    if (! $this->looksLikeGitRepository($repositoryPath)) {
      $this->append($output, 'Git push protection skipped: current install is not a git working copy.');

      return false;
    }

    $originUrl = $this->readGitConfig($repositoryPath, 'remote.origin.url');

    if ($originUrl === null) {
      $this->append($output, 'Git push protection skipped: origin remote is not configured.');

      return false;
    }

    if (! $this->isCanonicalUpstreamUrl($originUrl)) {
      $this->append($output, 'Git push protection skipped: origin does not point at the canonical WebBlocks CMS upstream.');

      return false;
    }

    $pushUrl = $this->readGitConfig($repositoryPath, 'remote.origin.pushurl');

    if ($this->isDisabledPushUrl($pushUrl)) {
      $this->append($output, 'Git push protection already enabled for origin.');

      return true;
    }

    $result = $this->runGitCommand($repositoryPath, [
      'git',
      'remote',
      'set-url',
      '--push',
      'origin',
      $this->disabledPushUrl(),
    ]);

    if (! $result->successful()) {
      $this->append($output, 'Warning: failed to disable git push for origin: '.$this->processError($result));

      return false;
    }

    $this->append($output, 'Disabled git push for origin while keeping fetch updates enabled.');

    return true;
  }

  public function protectCurrentInstallQuietly(?string $repositoryPath = null): void
  {
    $output = [];

    $this->protectCurrentInstall($repositoryPath, $output);

    foreach ($output as $line) {
      Log::info($line, ['repository_path' => $repositoryPath ?? base_path()]);
    }
  }

  public function isCanonicalUpstreamUrl(string $url): bool
  {
    $normalizedUrl = $this->normalizeGitUrl($url);

    if ($normalizedUrl === null) {
      return false;
    }

    return in_array($normalizedUrl, $this->canonicalUpstreamUrls(), true);
  }

  public function normalizeGitUrl(?string $url): ?string
  {
    if (! is_string($url)) {
      return null;
    }

    $url = trim($url);

    if ($url === '') {
      return null;
    }

    if (preg_match('/^(?<user>[^@]+)@(?<host>[^:]+):(?<path>.+)$/', $url, $matches) === 1) {
      $url = 'ssh://'.$matches['user'].'@'.$matches['host'].'/'.$matches['path'];
    }

    $parts = parse_url($url);

    if (! is_array($parts)) {
      return null;
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));
    $path = trim((string) ($parts['path'] ?? ''), '/');

    if ($scheme === '' || $host === '' || $path === '') {
      return null;
    }

    if (str_ends_with($path, '.git')) {
      $path = substr($path, 0, -4);
    }

    return $scheme.'://'.$host.'/'.strtolower($path);
  }

  private function canonicalUpstreamUrls(): array
  {
    return array_values(array_filter(array_map(
      fn (mixed $url): ?string => $this->normalizeGitUrl(is_string($url) ? $url : null),
      (array) config('cms.install.git_protection.canonical_upstream_urls', []),
    )));
  }

  private function disabledPushUrl(): string
  {
    return (string) config('cms.install.git_protection.disabled_push_url', 'DISABLED');
  }

  private function looksLikeGitRepository(string $repositoryPath): bool
  {
    return is_dir($repositoryPath.DIRECTORY_SEPARATOR.'.git') || is_file($repositoryPath.DIRECTORY_SEPARATOR.'.git');
  }

  private function readGitConfig(string $repositoryPath, string $key): ?string
  {
    $result = $this->runGitCommand($repositoryPath, ['git', 'config', '--get', $key]);

    if (! $result->successful()) {
      return null;
    }

    $value = trim($result->output());

    return $value === '' ? null : $value;
  }

  private function isDisabledPushUrl(?string $pushUrl): bool
  {
    if (! is_string($pushUrl)) {
      return false;
    }

    return in_array(strtolower(trim($pushUrl)), ['disabled', 'no_push'], true);
  }

  private function runGitCommand(string $repositoryPath, array $command): ProcessResult
  {
    return Process::path($repositoryPath)
      ->timeout((int) config('cms.install.git_protection.timeout_seconds', 15))
      ->run($command);
  }

  private function processError(ProcessResult $result): string
  {
    $error = trim($result->errorOutput());

    if ($error !== '') {
      return $error;
    }

    $output = trim($result->output());

    return $output !== '' ? $output : 'unknown git error';
  }

  private function append(array &$output, string $line): void
  {
    $output[] = $line;
  }
}
