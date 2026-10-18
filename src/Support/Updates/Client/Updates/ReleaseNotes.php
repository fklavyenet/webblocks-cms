<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Updates;

/** Concise optional section; historical changelogs keep their existing fallback. */
final class ReleaseNotes
{
    public static function conciseSection(string $body): ?string
    {
        return preg_match('/^###\s+Release notes\s*\R(?<notes>.*?)(?=^###\s|\z)/ims', $body, $match) === 1 ? trim($match['notes']) : null;
    }

    public static function bullets(string $body): array
    {
        $items = [];
        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            if (preg_match('/^\s*[-*]\s+(.+)$/', $line, $match)) { $items[] = trim($match[1]); }
            elseif (trim($line) !== '' && $items !== [] && !str_starts_with(ltrim($line), '#')) { $items[array_key_last($items)] .= ' '.trim($line); }
        }
        $seen = []; $result = [];
        foreach ($items as $item) {
            $key = mb_strtolower((string) preg_replace('/\s+/u', ' ', $item));
            if (!isset($seen[$key])) { $seen[$key] = true; $result[] = $item; }
        }
        return $result;
    }
}
