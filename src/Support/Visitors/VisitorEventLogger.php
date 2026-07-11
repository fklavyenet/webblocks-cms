<?php

namespace WebBlocks\Cms\Support\Visitors;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\VisitorEvent;
use WebBlocks\Cms\Support\Sites\SiteDomainNormalizer;

class VisitorEventLogger
{
  private const SESSION_KEY = 'cms.visitor_reports.session_key';

  public function __construct(private readonly VisitorConsent $visitorConsent) {}

  public function logPageView(Request $request, Page $page): void
  {
    if (! config('cms.visitor_reports.enabled', true) || $this->shouldIgnore($request)) {
      return;
    }

    $translation = $page->getRelation('currentTranslation');
    $trackingMode = $this->supportsTrackingMode()
      ? $this->visitorConsent->trackingMode($request)
      : VisitorEvent::TRACKING_MODE_FULL;
    $device = $this->deviceContext((string) $request->userAgent());
    $referrer = $this->referrerContext($request, $page);
    $payload = [
      'site_id' => $page->site_id,
      'page_id' => $page->id,
      'locale_id' => $translation?->locale_id,
      'path' => $this->normalizePath($request->getPathInfo()),
      'referrer' => $referrer['host'],
      'utm_source' => $this->utmValue($request, 'utm_source'),
      'utm_medium' => $this->utmValue($request, 'utm_medium'),
      'utm_campaign' => $this->utmValue($request, 'utm_campaign'),
      'device_type' => $device['device_type'],
      'browser_family' => $device['browser_family'],
      'os_family' => $device['os_family'],
      'visited_at' => now(),
    ];

    if ($this->supportsTrackingMode()) {
      $payload['tracking_mode'] = $trackingMode;
    }

    if ($this->supportsColumn('referrer_host')) {
      $payload['referrer_host'] = $referrer['host'];
    }

    if ($this->supportsColumn('referrer_type')) {
      $payload['referrer_type'] = $referrer['type'];
    }

    if ($this->supportsColumn('is_bot')) {
      $payload['is_bot'] = $device['device_type'] === 'bot';
    }

    if ($trackingMode === VisitorEvent::TRACKING_MODE_FULL) {
      $payload = [
        ...$payload,
        'session_key' => $this->sessionKey($request),
        'ip_hash' => $this->ipHash($request),
      ];
    }

    try {
      VisitorEvent::query()->create($payload);
    } catch (Throwable $exception) {
      report($exception);
    }
  }

  private function shouldIgnore(Request $request): bool
  {
    if ($request->is('admin') || $request->is('admin/*') || $request->is('api/*')) {
      return true;
    }

    return false;
  }

  private function normalizePath(?string $path): string
  {
    $normalized = '/'.ltrim((string) $path, '/');

    return $normalized === '//' ? '/' : $normalized;
  }

  private function sessionKey(Request $request): string
  {
    if (! $request->hasSession()) {
      return substr(hash('sha256', (string) $request->ip().(string) $request->userAgent()), 0, 40);
    }

    $session = $request->session();
    $sessionKey = $session->get(self::SESSION_KEY);

    if (is_string($sessionKey) && $sessionKey !== '') {
      return $sessionKey;
    }

    $sessionKey = bin2hex(random_bytes(20));
    $session->put(self::SESSION_KEY, $sessionKey);

    return $sessionKey;
  }

  private function ipHash(Request $request): ?string
  {
    $ip = trim((string) $request->ip());

    if ($ip === '') {
      return null;
    }

    $secret = (string) (config('app.key') ?: config('app.name'));

    return hash_hmac('sha256', $ip, $secret);
  }

  private function deviceContext(string $userAgent): array
  {
    $normalized = strtolower($userAgent);

    return [
      'device_type' => $this->isBot($normalized) ? 'bot' : $this->deviceType($normalized),
      'browser_family' => $this->isBot($normalized) ? null : $this->browserFamily($normalized),
      'os_family' => $this->isBot($normalized) ? null : $this->osFamily($normalized),
    ];
  }

  private function deviceType(string $userAgent): ?string
  {
    if ($userAgent === '') {
      return null;
    }

    foreach (['ipad', 'tablet', 'kindle', 'silk/', 'playbook', 'sm-t', 'nexus 7', 'nexus 10'] as $fragment) {
      if (str_contains($userAgent, $fragment)) {
        return 'tablet';
      }
    }

    foreach (['mobile', 'iphone', 'ipod', 'android', 'windows phone'] as $fragment) {
      if (str_contains($userAgent, $fragment)) {
        return 'mobile';
      }
    }

    return 'desktop';
  }

  private function browserFamily(string $userAgent): ?string
  {
    if ($userAgent === '') {
      return null;
    }

    return match (true) {
      str_contains($userAgent, 'edg/') => 'Edge',
      str_contains($userAgent, 'opr/'), str_contains($userAgent, 'opera') => 'Opera',
      str_contains($userAgent, 'firefox'), str_contains($userAgent, 'fxios') => 'Firefox',
      str_contains($userAgent, 'chrome'), str_contains($userAgent, 'crios') => 'Chrome',
      str_contains($userAgent, 'safari') => 'Safari',
      str_contains($userAgent, 'msie'), str_contains($userAgent, 'trident/') => 'Internet Explorer',
      default => null,
    };
  }

  private function osFamily(string $userAgent): ?string
  {
    if ($userAgent === '') {
      return null;
    }

    return match (true) {
      str_contains($userAgent, 'windows') => 'Windows',
      str_contains($userAgent, 'iphone'), str_contains($userAgent, 'ipad'), str_contains($userAgent, 'ios') => 'iOS',
      str_contains($userAgent, 'android') => 'Android',
      str_contains($userAgent, 'mac os x'), str_contains($userAgent, 'macintosh') => 'macOS',
      str_contains($userAgent, 'linux') => 'Linux',
      default => null,
    };
  }

  private function isBot(string $userAgent): bool
  {
    $normalized = strtolower($userAgent);

    if ($normalized === '') {
      return false;
    }

    foreach ((array) config('cms.visitor_reports.ignored_user_agents', []) as $fragment) {
      if (str_contains($normalized, strtolower((string) $fragment))) {
        return true;
      }
    }

    return false;
  }

  private function referrerContext(Request $request, Page $page): array
  {
    $host = $this->normalizedHost($request->headers->get('referer'));

    if ($host === null) {
      return ['host' => null, 'type' => 'direct'];
    }

    return [
      'host' => $host,
      'type' => $this->isInternalReferrer($host, $request, $page) ? 'internal' : 'external',
    ];
  }

  private function normalizedHost(mixed $value): ?string
  {
    $normalized = trim((string) $value);

    if ($normalized === '') {
      return null;
    }

    $host = parse_url($normalized, PHP_URL_HOST);
    $host = is_string($host) && $host !== '' ? $host : $normalized;
    $host = app(SiteDomainNormalizer::class)->normalize($host);

    return $host !== null ? $this->truncate($host, 255) : null;
  }

  private function isInternalReferrer(string $host, Request $request, Page $page): bool
  {
    $requestHost = app(SiteDomainNormalizer::class)->normalize($request->getHost());

    if ($requestHost !== null && $host === $requestHost) {
      return true;
    }

    $site = $page->site;
    $siteHost = $site ? app(SiteDomainNormalizer::class)->normalize($site->canonicalDomain()) : null;

    if ($siteHost !== null && $host === $siteHost) {
      return true;
    }

    if ($site?->relationLoaded('siteDomains')) {
      return $site->siteDomains->contains(fn ($domain) => $domain->domain === $host);
    }

    return $site?->siteDomains()->where('domain', $host)->exists() ?? false;
  }

  private function truncate(mixed $value, int $limit = 255): ?string
  {
    $normalized = trim((string) $value);

    if ($normalized === '') {
      return null;
    }

    return mb_substr($normalized, 0, $limit);
  }

  private function utmValue(Request $request, string $key): ?string
  {
    if (! config('cms.visitor_reports.utm_enabled', true)) {
      return null;
    }

    $normalized = $this->truncate($request->query($key));

    if ($normalized === null) {
      return null;
    }

    $sanitized = Str::of($normalized)
      ->replaceMatches('/[[:cntrl:]]+/u', ' ')
      ->squish()
      ->value();

    return $sanitized !== '' ? $sanitized : null;
  }

  private function supportsTrackingMode(): bool
  {
    return $this->supportsColumn('tracking_mode');
  }

  private function supportsColumn(string $column): bool
  {
    return Schema::hasTable('wbcms_visitor_events') && Schema::hasColumn('wbcms_visitor_events', $column);
  }
}
