<?php

namespace App\Http\Controllers;

use App\Support\Visitors\VisitorConsent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PublicPrivacyConsentController extends Controller
{
    public function __construct(private readonly VisitorConsent $visitorConsent) {}

    public function accept(Request $request): RedirectResponse
    {
        return $this->redirectWithConsent($request, VisitorConsent::ACCEPTED);
    }

    public function decline(Request $request): RedirectResponse
    {
        return $this->redirectWithConsent($request, VisitorConsent::DECLINED);
    }

    private function redirectWithConsent(Request $request, string $decision): RedirectResponse
    {
        $request->validate([
            'redirect_to' => ['nullable', 'string', 'max:2048'],
        ]);

        return redirect($this->safeRedirectTarget($request))
            ->cookie($this->visitorConsent->consentCookie($decision));
    }

    private function safeRedirectTarget(Request $request): string
    {
        $target = trim((string) $request->input('redirect_to', '/'));

        if ($target === '' || str_starts_with($target, '//')) {
            return '/';
        }

        $parts = parse_url($target);

        if ($parts === false) {
            return '/';
        }

        if (! isset($parts['scheme'], $parts['host'])) {
            return '/'.ltrim($target, '/');
        }

        if (! in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)) {
            return '/';
        }

        if (strcasecmp((string) $parts['host'], $request->getHost()) !== 0) {
            return '/';
        }

        return $target;
    }
}
