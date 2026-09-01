<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

namespace WebBlocks\Cms\Support\Updates\Client\Updates;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Pre-run reachability check for the private Composer repository (1.0.2).
 *
 * On every full-root consumer the post-apply `composer install` fetches the
 * engine from the private Composer repo, so a missing/wrong auth.json or an
 * unreachable repo fails the run only AFTER download+backup+apply. This
 * check asks the repo for its packages.json with the credentials composer
 * itself would use, so the update screen can warn BEFORE the run.
 *
 * Advisory only — it never blocks the run (composer may still succeed via
 * cache, COMPOSER_AUTH, …). Results are cached briefly so the screen stays
 * fast. Irrelevant (and skipped) when `apply.composer_install` is false or
 * the root composer.json has no composer-type repository.
 */
class ComposerRepoPreflight
{
  public function __construct(private readonly ?string $rootPath = null)
  {
  }

  /**
   * @return array{relevant: bool, ok: bool, issues: list<array{key: string, url: string, detail: string|null}>}
   */
  public function check(): array
  {
    if (! (bool) config('publisher-client.apply.composer_install', false)) {
      return $this->result(relevant: false);
    }

    $repos = $this->composerRepositories();

    if ($repos === []) {
      return $this->result(relevant: false);
    }

    $ttl = max(0, (int) config('publisher-client.preflight.cache_ttl_seconds', 300));

    if ($ttl === 0) {
      return $this->probe($repos);
    }

    return Cache::remember('publisher-client:composer-preflight', $ttl, fn (): array => $this->probe($repos));
  }

  /**
   * @param  list<string>  $repos
   * @return array{relevant: bool, ok: bool, issues: list<array{key: string, url: string, detail: string|null}>}
   */
  private function probe(array $repos): array
  {
    $issues = [];

    foreach ($repos as $url) {
      $issue = $this->probeRepository($url);

      if ($issue !== null) {
        $issues[] = $issue;
      }
    }

    return $this->result(relevant: true, issues: $issues);
  }

  /** @return array{key: string, url: string, detail: string|null}|null */
  private function probeRepository(string $url): ?array
  {
    $auth = $this->basicAuthFor($url);

    try {
      $request = Http::connectTimeout(3)->timeout(5)->withHeaders(['Accept' => 'application/json']);

      if ($auth !== null) {
        $request = $request->withBasicAuth($auth['username'], $auth['password']);
      }

      $response = $request->get(rtrim($url, '/').'/packages.json');
    } catch (Throwable $throwable) {
      return ['key' => 'unreachable', 'url' => $url, 'detail' => $throwable->getMessage()];
    }

    if ($response->successful()) {
      return null;
    }

    if (in_array($response->status(), [401, 403], true)) {
      return [
        'key' => 'unauthenticated',
        'url' => $url,
        'detail' => $auth === null ? 'no auth.json entry for this host' : 'HTTP '.$response->status(),
      ];
    }

    return ['key' => 'unreachable', 'url' => $url, 'detail' => 'HTTP '.$response->status()];
  }

  /**
   * Composer-type repository URLs from the root composer.json.
   *
   * @return list<string>
   */
  private function composerRepositories(): array
  {
    $decoded = $this->readJson($this->root().'/composer.json');

    $urls = [];

    foreach ((array) ($decoded['repositories'] ?? []) as $repository) {
      if (! is_array($repository) || ($repository['type'] ?? null) !== 'composer') {
        continue;
      }

      $url = trim((string) ($repository['url'] ?? ''));

      if ($url !== '' && str_starts_with($url, 'http')) {
        $urls[] = $url;
      }
    }

    return array_values(array_unique($urls));
  }

  /**
   * The http-basic credentials composer itself would send: the repo host's
   * entry from the project auth.json (the fleet-standard location) or the
   * COMPOSER_HOME fallback is intentionally NOT consulted — a passing check
   * must prove the credentials the unattended run will actually have.
   *
   * @return array{username: string, password: string}|null
   */
  private function basicAuthFor(string $url): ?array
  {
    $host = (string) parse_url($url, PHP_URL_HOST);

    if ($host === '') {
      return null;
    }

    $entry = $this->readJson($this->root().'/auth.json')['http-basic'][$host] ?? null;

    if (! is_array($entry)) {
      return null;
    }

    $username = (string) ($entry['username'] ?? '');
    $password = (string) ($entry['password'] ?? '');

    if ($username === '' || $password === '') {
      return null;
    }

    return ['username' => $username, 'password' => $password];
  }

  /** @return array<string, mixed> */
  private function readJson(string $path): array
  {
    if (! is_file($path) || ! is_readable($path)) {
      return [];
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    return is_array($decoded) ? $decoded : [];
  }

  private function root(): string
  {
    return rtrim($this->rootPath ?? base_path(), '/');
  }

  /**
   * @param  list<array{key: string, url: string, detail: string|null}>  $issues
   * @return array{relevant: bool, ok: bool, issues: list<array{key: string, url: string, detail: string|null}>}
   */
  private function result(bool $relevant, array $issues = []): array
  {
    return [
      'relevant' => $relevant,
      'ok' => $issues === [],
      'issues' => $issues,
    ];
  }
}
