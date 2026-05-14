<?php

namespace App\Support\Media;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class MediaIndexState
{
    /**
     * @var array<int, string>
     */
    private const ALLOWED_QUERY_KEYS = [
        'folder_id',
        'search',
        'kind',
        'usage',
        'view',
        'page',
    ];

    public function returnUrl(Request $request): string
    {
        return $this->safeReturnUrlFromRequest($request)
            ?? route('admin.media.index');
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

        $expectedPath = rtrim(route('admin.media.index', [], false), '/');
        $path = rtrim((string) ($parsed['path'] ?? ''), '/');

        if ($path !== $expectedPath) {
            return null;
        }

        parse_str((string) ($parsed['query'] ?? ''), $query);

        if (! is_array($query)) {
            return null;
        }

        return route('admin.media.index', $this->normalizeQuery($query));
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

            if ($key === 'page' && (! ctype_digit($stringValue) || (int) $stringValue < 2)) {
                continue;
            }

            if ($key === 'view' && ! in_array($stringValue, ['list', 'grid'], true)) {
                continue;
            }

            if ($key === 'kind' && ! in_array($stringValue, ['image', 'video', 'document', 'other'], true)) {
                continue;
            }

            if ($key === 'usage' && ! in_array($stringValue, ['used', 'unused'], true)) {
                continue;
            }

            $normalized[$key] = $stringValue;
        }

        return $normalized;
    }
}
