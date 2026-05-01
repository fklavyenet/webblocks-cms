<?php

namespace Tests\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

trait HtmlStructureAssertions
{
    protected function assertElementTag(string $html, string $selector, string $expectedTag): void
    {
        $elements = $this->elementsForSelector($html, $selector);

        $this->assertNotEmpty($elements, "Failed asserting that selector [{$selector}] exists.");

        foreach ($elements as $element) {
            $this->assertSame(
                strtolower($expectedTag),
                strtolower($element->tagName),
                "Failed asserting that selector [{$selector}] uses <{$expectedTag}>.",
            );
        }
    }

    protected function assertElementNotTag(string $html, string $selector, string $unexpectedTag): void
    {
        $elements = $this->elementsForSelector($html, $selector);

        foreach ($elements as $element) {
            $this->assertNotSame(
                strtolower($unexpectedTag),
                strtolower($element->tagName),
                "Failed asserting that selector [{$selector}] does not use <{$unexpectedTag}>.",
            );
        }
    }

    protected function assertElementStructure(string $html, array $structure): void
    {
        foreach ($structure as $selector => $expectedTag) {
            $this->assertElementTag($html, $selector, $expectedTag);
        }
    }

    /**
     * @return list<DOMElement>
     */
    private function elementsForSelector(string $html, string $selector): array
    {
        $dom = new DOMDocument('1.0', 'UTF-8');
        $internalErrors = libxml_use_internal_errors(true);

        $dom->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        $xpath = new DOMXPath($dom);
        $query = $this->selectorToXPath($selector);

        return iterator_to_array($xpath->query($query) ?: []);
    }

    private function selectorToXPath(string $selector): string
    {
        $segments = preg_split('/\s+/', trim($selector)) ?: [];
        $parts = [];

        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }

            preg_match_all('/\.([A-Za-z0-9_-]+)/', $segment, $matches);
            $classes = $matches[1] ?? [];

            if ($classes === []) {
                throw new \InvalidArgumentException("Only class selectors are supported. Invalid selector [{$selector}].");
            }

            $conditions = array_map(
                fn (string $class) => "contains(concat(' ', normalize-space(@class), ' '), ' {$class} ')",
                $classes,
            );

            $parts[] = '*['.implode(' and ', $conditions).']';
        }

        return '//'.implode('//', $parts);
    }
}
