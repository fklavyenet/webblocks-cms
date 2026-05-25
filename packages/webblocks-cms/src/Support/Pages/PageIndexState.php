<?php

namespace WebBlocks\Cms\Support\Pages;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class PageIndexState
{
  public const SESSION_KEY = 'admin.pages.index_state';

  /**
     * @var array<int, string>
     */
  private const ALLOWED_QUERY_KEYS = [
    'site',
    'site_id',
    'search',
    'status',
    'sort',
    'direction',
    'page',
  ];

  public function remember(Request $request, array $query): void
  {
    if (! $request->hasSession()) {
      return;
    }

    $normalized = $this->normalizeQuery($query);

    if ($normalized === []) {
      $request->session()->forget(self::SESSION_KEY);

      return;
    }

    $request->session()->put(self::SESSION_KEY, $normalized);
  }

  public function forget(Request $request): void
  {
    if (! $request->hasSession()) {
      return;
    }

    $request->session()->forget(self::SESSION_KEY);
  }

  public function storedQuery(Request $request): array
  {
    if (! $request->hasSession()) {
      return [];
    }

    $query = $request->session()->get(self::SESSION_KEY);

    return is_array($query) ? $this->normalizeQuery($query) : [];
  }

  public function returnUrl(Request $request, ?int $fallbackSiteId = null): string
  {
    return $this->safeReturnUrlFromRequest($request)
      ?? $this->storedUrl($request, $fallbackSiteId);
  }

  public function storedUrl(Request $request, ?int $fallbackSiteId = null): string
  {
    $query = $this->storedQuery($request);

    if ($query === [] && $fallbackSiteId) {
      $query['site'] = (string) $fallbackSiteId;
    }

    return route('admin.pages.index', $query);
  }

  public function safeReturnUrlFromRequest(Request $request): ?string
  {
    $candidate = $request->input('return_url', $request->query('return_url'));

    return is_string($candidate) ? $this->sanitizeReturnUrl($candidate) : null;
  }

  public function sanitizeReturnUrl(?string $candidate): ?string
  {
    $candidate = trim((string) $candidate);

    if ($candidate === '') {
      return null;
    }

    $parsed = parse_url($candidate);

    if ($parsed === false) {
      return null;
    }

    $appUrl = parse_url(URL::to('/')) ?: [];
    $candidateHasAuthority = isset($parsed['host']) || isset($parsed['scheme']) || isset($parsed['port']) || isset($parsed['user']) || isset($parsed['pass']);

    if ($candidateHasAuthority) {
      if (($parsed['scheme'] ?? null) !== ($appUrl['scheme'] ?? null)) {
        return null;
      }

      if (($parsed['host'] ?? null) !== ($appUrl['host'] ?? null)) {
        return null;
      }

      if (($parsed['port'] ?? null) !== ($appUrl['port'] ?? null)) {
        return null;
      }

      if (isset($parsed['user']) || isset($parsed['pass'])) {
        return null;
      }
    }

    $expectedPath = rtrim(route('admin.pages.index', [], false), '/');
    $path = rtrim((string) ($parsed['path'] ?? ''), '/');

    if ($path !== $expectedPath) {
      return null;
    }

    parse_str((string) ($parsed['query'] ?? ''), $query);

    if (! is_array($query)) {
      return null;
    }

    return route('admin.pages.index', $this->normalizeQuery($query));
  }

  public function normalizeQuery(array $query): array
  {
    $normalized = [];

    foreach (self::ALLOWED_QUERY_KEYS as $key) {
      if (! array_key_exists($key, $query)) {
        continue;
      }

      $value = $query[$key];

      if (is_array($value) || is_object($value)) {
        continue;
      }

      $stringValue = trim((string) $value);

      if ($stringValue === '') {
        continue;
      }

      if ($key === 'page') {
        if (! ctype_digit($stringValue) || (int) $stringValue < 2) {
          continue;
        }
      }

      $normalized[$key] = $stringValue;
    }

    return $normalized;
  }
}
