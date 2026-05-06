<?php

namespace App\Support\Search;

use Illuminate\Support\Str;

class SearchTextNormalizer
{
    public function normalize(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_array($value)) {
            return $this->join($value);
        }

        $text = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return trim($text);
    }

    public function join(iterable $parts): string
    {
        $resolved = collect($parts)
            ->map(fn ($part) => $this->normalize($part))
            ->filter()
            ->values()
            ->implode(' ');

        return $this->normalize($resolved);
    }

    public function query(?string $query): string
    {
        return $this->normalize($query);
    }

    public function excerpt(mixed $value, ?string $query = null, int $length = 220): ?string
    {
        $text = $this->normalize($value);

        if ($text === '') {
            return null;
        }

        $normalizedQuery = $this->query($query);

        if ($normalizedQuery === '') {
            return Str::limit($text, $length);
        }

        $matchPosition = mb_stripos(mb_strtolower($text), mb_strtolower($normalizedQuery));

        if ($matchPosition === false) {
            return Str::limit($text, $length)->toString();
        }

        $start = max($matchPosition - 60, 0);
        $excerpt = mb_substr($text, $start, $length);
        $excerpt = trim($excerpt);

        if ($start > 0) {
            $excerpt = '... '.$excerpt;
        }

        if (($start + mb_strlen($excerpt)) < mb_strlen($text)) {
            $excerpt = rtrim($excerpt, '. ').'...';
        }

        return $excerpt;
    }
}
