<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Updates;

use Illuminate\Support\Arr;
use WebBlocks\Cms\Support\Updates\Client\PublisherClient;

/** One presentation rule for Composer and embedded product adapters. */
final class ReleaseDetails
{
    private const GROUPS = [
        'highlights' => 'Highlights', 'fixes' => 'Fixes',
        'compatibility_notes' => 'Compatibility notes', 'migration_notes' => 'Migration notes',
        'asset_notes' => 'Asset notes', 'operator_notes' => 'Operator notes', 'technical_notes' => 'Technical notes',
    ];

    public static function normalize(array $release): array
    {
        $value = static function (string $key) use ($release): mixed {
            foreach (['release_details.', 'details.', 'meta.release_details.', 'meta.details.', ''] as $prefix) {
                if (Arr::has($release, $prefix.$key)) { return Arr::get($release, $prefix.$key); }
            }
            return null;
        };
        $text = static fn ($item): ?string => is_string($item) && trim($item) !== '' ? trim($item) : null;
        $title = $text($value('title'));
        $summary = $text($value('summary'));
        $seen = $summary === null ? [] : [self::key($summary) => true];
        $groups = [];
        foreach (self::GROUPS as $key => $label) {
            $items = [];
            foreach (self::items($value($key)) as $item) {
                $identity = self::key($item);
                if (!isset($seen[$identity])) { $items[] = $item; $seen[$identity] = true; }
            }
            if ($items !== []) { $groups[] = compact('key', 'label', 'items'); }
        }
        // Flat notes are a compatibility fallback, never a second rendering of details.
        $fallback = ($summary !== null || $groups !== []) ? [] : self::items($release['release_notes'] ?? null);
        $seenFallback = [];
        $fallback = array_values(array_filter($fallback, static function (string $item) use (&$seenFallback): bool {
            $key = self::key($item);
            if (isset($seenFallback[$key])) { return false; }
            $seenFallback[$key] = true; return true;
        }));
        if ($summary === null && $groups === [] && $fallback !== []) { $summary = array_shift($fallback); }
        $hasNotes = $title !== null || $summary !== null || $groups !== [] || $fallback !== [];
        if ($hasNotes) {
            $engine = sprintf('Installed update engine: Publisher Client %s (%s).', PublisherClient::VERSION, (string) config('publisher-client.distribution', 'composer'));
            $index = array_search('technical_notes', array_column($groups, 'key'), true);
            if ($index === false) { $groups[] = ['key' => 'technical_notes', 'label' => self::GROUPS['technical_notes'], 'items' => [$engine]]; }
            elseif (!in_array($engine, $groups[$index]['items'], true)) { $groups[$index]['items'][] = $engine; }
        }
        return ['title' => $title, 'summary' => $summary, 'groups' => $groups, 'fallback_notes' => $fallback, 'has_notes' => $hasNotes];
    }

    private static function items(mixed $value): array
    {
        if (is_array($value)) { return array_merge([], ...array_map(self::items(...), array_values($value))); }
        if (!is_string($value)) { return []; }
        return array_values(array_filter(array_map(static fn ($line) => trim((string) preg_replace('/^\s*(?:[-*]|\d+[.)])\s+/', '', trim($line))), preg_split('/\R/', $value) ?: []), static fn ($line) => $line !== ''));
    }

    private static function key(string $text): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim($text)));
    }
}
