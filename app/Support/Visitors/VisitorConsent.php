<?php

namespace App\Support\Visitors;

use App\Models\VisitorEvent;
use App\Support\System\SystemSettings;
use Illuminate\Cookie\CookieJar;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

class VisitorConsent
{
    public const ACCEPTED = 'accepted';

    public const DECLINED = 'declined';

    public function __construct(
        private readonly CookieJar $cookies,
        private readonly SystemSettings $systemSettings,
    ) {}

    public function cookieName(): string
    {
        return (string) config('cms.visitor_reports.consent_cookie_name', 'webblocks_visitor_consent');
    }

    public function hasStoredChoice(Request $request): bool
    {
        return in_array($this->value($request), [self::ACCEPTED, self::DECLINED], true);
    }

    public function analyticsAccepted(Request $request): bool
    {
        return $this->value($request) === self::ACCEPTED;
    }

    public function trackingMode(Request $request): string
    {
        return $this->analyticsAccepted($request)
            ? VisitorEvent::TRACKING_MODE_FULL
            : VisitorEvent::TRACKING_MODE_BASIC;
    }

    public function bannerEnabled(): bool
    {
        return (bool) config('cms.visitor_reports.enabled', true)
            && $this->systemSettings->visitorConsentBannerEnabled();
    }

    public function shouldShowBanner(Request $request): bool
    {
        if (! $this->bannerEnabled()) {
            return false;
        }

        return ! $this->hasStoredChoice($request);
    }

    public function consentCookie(string $value): Cookie
    {
        return $this->cookies->make(
            $this->cookieName(),
            $value,
            $this->cookieLifetimeMinutes(),
            '/',
            null,
            null,
            true,
            false,
            'lax',
        );
    }

    private function value(Request $request): ?string
    {
        $value = trim((string) $request->cookie($this->cookieName()));

        return $value !== '' ? $value : null;
    }

    private function cookieLifetimeMinutes(): int
    {
        return max(1, (int) config('cms.visitor_reports.consent_cookie_lifetime_days', 180)) * 24 * 60;
    }
}
