<?php

namespace WebBlocks\Cms\Support\Contact;

class ContactFormRedirects
{
    public function target(?string $sourceUrl, int|string|null $blockId, ?string $fallbackUrl = null): string
    {
        return $this->baseUrl($sourceUrl, $fallbackUrl).'#contact-form-'.((int) $blockId);
    }

    public function baseUrl(?string $sourceUrl, ?string $fallbackUrl = null): string
    {
        return $this->safeLocalBase($sourceUrl)
            ?? $this->safeLocalBase($fallbackUrl)
            ?? url('/');
    }

    private function safeLocalBase(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $this->withoutFragment($url);
        }

        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);

        if ($host !== request()->getHost()) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);

        return $path.($query ? '?'.$query : '');
    }

    private function withoutFragment(string $url): string
    {
        return explode('#', $url, 2)[0];
    }
}
