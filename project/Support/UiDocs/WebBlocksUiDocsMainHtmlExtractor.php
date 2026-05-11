<?php

namespace Project\Support\UiDocs;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Str;
use RuntimeException;

class WebBlocksUiDocsMainHtmlExtractor
{
    private const SOURCE_BASE = 'https://ui.webblocksui.com';

    public function extract(string $html, string $sourceUrl): string
    {
        $document = $this->loadDocument($html);
        $main = $this->resolveMain($document);

        if (! $main) {
            throw new RuntimeException('Could not extract docs main HTML from source page.');
        }

        $this->stripUnwantedNodes($main);
        $this->rewriteLinks($main, $sourceUrl);

        $fragment = $this->innerHtml($main);
        $overlay = $this->overlayHtml($document, $sourceUrl);

        if ($overlay !== null) {
            $fragment .= PHP_EOL.$overlay;
        }

        $fragment = trim($fragment);

        if ($fragment === '') {
            throw new RuntimeException('Docs main HTML extraction returned an empty fragment.');
        }

        return $fragment;
    }

    private function loadDocument(string $html): DOMDocument
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $loaded) {
            throw new RuntimeException('Could not parse source HTML for docs import.');
        }

        return $document;
    }

    private function resolveMain(DOMDocument $document): ?DOMElement
    {
        $xpath = new DOMXPath($document);

        foreach ([
            '//main',
            '//*[contains(concat(" ", normalize-space(@class), " "), " wb-dashboard-main ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " wb-docs-main ")]',
            '//*[contains(concat(" ", normalize-space(@class), " "), " wb-content-shell ")]',
        ] as $query) {
            $candidate = $xpath->query($query)->item(0);

            if ($candidate instanceof DOMElement) {
                return $candidate;
            }
        }

        return null;
    }

    private function stripUnwantedNodes(DOMElement $main): void
    {
        $document = $main->ownerDocument;

        if (! $document) {
            return;
        }

        $xpath = new DOMXPath($document);
        $queries = [
            './/script',
            './/noscript',
            './/style',
            './/link',
            './/header[contains(concat(" ", normalize-space(@class), " "), " wb-navbar ")]',
            './/aside[contains(concat(" ", normalize-space(@class), " "), " wb-sidebar ")]',
            './/nav[contains(concat(" ", normalize-space(@class), " "), " wb-sidebar-nav ")]',
            './/nav[contains(concat(" ", normalize-space(@class), " "), " wb-docs-breadcrumb ")]',
            './/footer[contains(concat(" ", normalize-space(@class), " "), " wb-sidebar-footer ")]',
        ];

        foreach ($queries as $query) {
            $nodes = [];

            foreach ($xpath->query($query, $main) ?: [] as $node) {
                if ($node instanceof DOMNode) {
                    $nodes[] = $node;
                }
            }

            foreach ($nodes as $node) {
                $node->parentNode?->removeChild($node);
            }
        }
    }

    private function rewriteLinks(DOMElement $main, string $sourceUrl): void
    {
        $document = $main->ownerDocument;

        if (! $document) {
            return;
        }

        $xpath = new DOMXPath($document);

        foreach ($xpath->query('.//*[@href]', $main) ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $rewritten = $this->rewriteUrl($node->getAttribute('href'), $sourceUrl);

            if ($rewritten !== null) {
                $node->setAttribute('href', $rewritten);
            }
        }

        foreach ($xpath->query('.//*[@src]', $main) ?: [] as $node) {
            if (! $node instanceof DOMElement) {
                continue;
            }

            $rewritten = $this->rewriteAssetUrl($node->getAttribute('src'), $sourceUrl);

            if ($rewritten !== null) {
                $node->setAttribute('src', $rewritten);
            }
        }
    }

    private function overlayHtml(DOMDocument $document, string $sourceUrl): ?string
    {
        $overlay = $document->getElementById('wb-overlay-root');

        if (! $overlay instanceof DOMElement) {
            return null;
        }

        $this->rewriteLinks($overlay, $sourceUrl);

        return trim($document->saveHTML($overlay) ?: '') ?: null;
    }

    private function rewriteUrl(string $value, string $sourceUrl): ?string
    {
        $value = trim($value);

        if ($value === '' || Str::startsWith($value, ['#', 'mailto:', 'tel:', 'javascript:'])) {
            return $value;
        }

        $absolute = $this->absoluteUrl($value, $sourceUrl);

        if ($absolute === null) {
            return $value;
        }

        $path = (string) parse_url($absolute, PHP_URL_PATH);
        $query = parse_url($absolute, PHP_URL_QUERY);
        $fragment = parse_url($absolute, PHP_URL_FRAGMENT);
        $suffix = ($query ? '?'.$query : '').($fragment ? '#'.$fragment : '');

        if ($path === '/playground/' || $path === '/playground') {
            return '../playground/'.$suffix;
        }

        if (! Str::startsWith($path, '/docs/')) {
            return $absolute;
        }

        $slug = Str::of($path)->after('/docs/')->beforeLast('.html')->trim('/')->toString();

        if ($slug === '') {
            return $absolute;
        }

        return '/p/'.$slug.$suffix;
    }

    private function rewriteAssetUrl(string $value, string $sourceUrl): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return $value;
        }

        $absolute = $this->absoluteUrl($value, $sourceUrl);

        return $absolute ?? $value;
    }

    private function absoluteUrl(string $value, string $sourceUrl): ?string
    {
        if (Str::startsWith($value, ['http://', 'https://'])) {
            return $value;
        }

        if (Str::startsWith($value, '//')) {
            return 'https:'.$value;
        }

        if (Str::startsWith($value, '/')) {
            return rtrim(self::SOURCE_BASE, '/').$value;
        }

        $baseDir = Str::beforeLast($sourceUrl, '/');

        if ($baseDir === $sourceUrl) {
            return null;
        }

        return $this->normalizePath($baseDir.'/'.$value);
    }

    private function normalizePath(string $url): string
    {
        $parts = parse_url($url);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? parse_url(self::SOURCE_BASE, PHP_URL_HOST);
        $path = $parts['path'] ?? '/';
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        $segments = [];

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                array_pop($segments);

                continue;
            }

            $segments[] = $segment;
        }

        return $scheme.'://'.$host.'/'.implode('/', $segments).$query.$fragment;
    }

    private function innerHtml(DOMElement $element): string
    {
        $html = '';

        foreach ($element->childNodes as $child) {
            $html .= $element->ownerDocument?->saveHTML($child) ?? '';
        }

        return $html;
    }
}
