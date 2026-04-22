<?php

namespace App\Support\Visitors;

use App\Models\Page;
use App\Models\VisitorEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class VisitorEventLogger
{
    private const SESSION_KEY = 'cms.visitor_reports.session_key';

    public function logPageView(Request $request, Page $page): void
    {
        if (! config('cms.visitor_reports.enabled', true) || $this->shouldIgnore($request)) {
            return;
        }

        $translation = $page->getRelation('currentTranslation');
        $userAgent = (string) $request->userAgent();
        $device = $this->deviceContext($userAgent);

        try {
            VisitorEvent::query()->create([
                'site_id' => $page->site_id,
                'page_id' => $page->id,
                'locale_id' => $translation?->locale_id,
                'path' => $this->normalizePath($request->getPathInfo()),
                'referrer' => $this->truncate($request->headers->get('referer'), 2048),
                'utm_source' => $this->utmValue($request, 'utm_source'),
                'utm_medium' => $this->utmValue($request, 'utm_medium'),
                'utm_campaign' => $this->utmValue($request, 'utm_campaign'),
                'device_type' => $device['device_type'],
                'browser_family' => $device['browser_family'],
                'os_family' => $device['os_family'],
                'session_key' => $this->sessionKey($request),
                'ip_hash' => $this->ipHash($request),
                'visited_at' => now(),
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function shouldIgnore(Request $request): bool
    {
        if ($request->is('admin') || $request->is('admin/*') || $request->is('api/*')) {
            return true;
        }

        return $this->isObviousBot((string) $request->userAgent());
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
            'device_type' => $this->deviceType($normalized),
            'browser_family' => $this->browserFamily($normalized),
            'os_family' => $this->osFamily($normalized),
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

    private function isObviousBot(string $userAgent): bool
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
}
