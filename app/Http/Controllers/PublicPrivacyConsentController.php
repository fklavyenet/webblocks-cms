<?php

namespace App\Http\Controllers;

use App\Support\Visitors\VisitorConsent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicPrivacyConsentController extends Controller
{
    public function __construct(private readonly VisitorConsent $visitorConsent) {}

    public function sync(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:accepted,rejected,custom'],
            'preferences' => ['required', 'array'],
            'preferences.necessary' => ['nullable', 'boolean'],
            'preferences.preferences' => ['nullable', 'boolean'],
            'preferences.analytics' => ['required', 'boolean'],
            'preferences.marketing' => ['nullable', 'boolean'],
        ]);

        $decision = $this->visitorConsent->decisionForAnalytics((bool) data_get($validated, 'preferences.analytics'));

        return response()->json([
            'status' => $validated['status'],
            'server_decision' => $decision,
        ])->cookie($this->visitorConsent->consentCookie($decision));
    }
}
