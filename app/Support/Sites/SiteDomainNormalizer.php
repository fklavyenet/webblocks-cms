<?php

namespace App\Support\Sites;

class SiteDomainNormalizer
{
    public function normalize(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim(strtolower($value));

        if ($normalized === '') {
            return null;
        }

        if (str_contains($normalized, '://')) {
            $host = parse_url($normalized, PHP_URL_HOST);
            $normalized = is_string($host) ? $host : '';
        }

        $normalized = trim($normalized, "/ \t\n\r\0\x0B");
        $normalized = preg_replace('/:\d+$/', '', $normalized) ?: '';

        if ($normalized === '' || str_contains($normalized, '/') || str_contains($normalized, '?') || str_contains($normalized, '#')) {
            return null;
        }

        return $normalized;
    }
}
