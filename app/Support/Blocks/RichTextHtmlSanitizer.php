<?php

namespace App\Support\Blocks;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Str;

class RichTextHtmlSanitizer
{
    private const BLOCK_TAGS = ['p', 'ul', 'ol', 'li', 'blockquote'];

    private const INLINE_TAGS = ['strong', 'em', 'code', 'a', 'br'];

    private const ALLOWED_TAGS = [
        'p',
        'br',
        'strong',
        'em',
        'code',
        'ul',
        'ol',
        'li',
        'blockquote',
        'a',
    ];

    private const DROP_WITH_CONTENT_TAGS = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'noscript'];

    public function sanitize(?string $html): ?string
    {
        $html = (string) ($html ?? '');

        if (trim($html) === '') {
            return null;
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $document = new DOMDocument('1.0', 'UTF-8');
            $document->loadHTML(
                '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body><div>'.$html.'</div></body></html>',
                LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
            );

            $root = $document->getElementsByTagName('div')->item(0);

            if (! $root instanceof DOMElement) {
                return null;
            }

            $this->sanitizeChildren($root);
            $this->normalizeRootChildren($root);

            $html = trim($this->innerHtml($root));

            return $this->containsMeaningfulText($root) ? $html : null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function sanitizeChildren(DOMNode $node): void
    {
        for ($child = $node->firstChild; $child !== null; $child = $next) {
            $next = $child->nextSibling;

            if ($child->nodeType === XML_COMMENT_NODE) {
                $node->removeChild($child);

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            $this->sanitizeElement($child);
        }
    }

    private function sanitizeElement(DOMElement $element): void
    {
        $tag = Str::lower($element->tagName);

        if ($tag === 'b') {
            $element = $this->renameElement($element, 'strong');
            $tag = 'strong';
        }

        if ($tag === 'i') {
            $element = $this->renameElement($element, 'em');
            $tag = 'em';
        }

        if ($tag === 'div') {
            $element = $this->renameElement($element, 'p');
            $tag = 'p';
        }

        if (in_array($tag, self::DROP_WITH_CONTENT_TAGS, true)) {
            $element->parentNode?->removeChild($element);

            return;
        }

        if (! in_array($tag, self::ALLOWED_TAGS, true)) {
            $this->unwrapElement($element);

            return;
        }

        $this->sanitizeChildren($element);
        $this->sanitizeAttributes($element, $tag);
    }

    private function sanitizeAttributes(DOMElement $element, string $tag): void
    {
        for ($index = $element->attributes->length - 1; $index >= 0; $index--) {
            $attribute = $element->attributes->item($index);

            if ($attribute === null) {
                continue;
            }

            $name = Str::lower($attribute->nodeName);

            if ($tag !== 'a' || ! in_array($name, ['href', 'target', 'rel'], true)) {
                $element->removeAttributeNode($attribute);
            }
        }

        if ($tag !== 'a') {
            return;
        }

        $href = trim((string) $element->getAttribute('href'));

        if (! $this->isAllowedHref($href)) {
            $element->removeAttribute('href');
            $element->removeAttribute('target');
            $element->removeAttribute('rel');

            return;
        }

        $element->setAttribute('href', $href);

        if ($element->getAttribute('target') === '_blank') {
            $element->setAttribute('target', '_blank');
            $element->setAttribute('rel', 'noopener noreferrer');

            return;
        }

        $element->removeAttribute('target');
        $element->removeAttribute('rel');
    }

    private function isAllowedHref(string $href): bool
    {
        if ($href === '' || preg_match('/\s/', $href) === 1) {
            return false;
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $href) === 1) {
            $scheme = Str::lower((string) parse_url($href, PHP_URL_SCHEME));

            if (in_array($scheme, ['http', 'https'], true)) {
                return filter_var($href, FILTER_VALIDATE_URL) !== false;
            }

            if ($scheme === 'mailto') {
                return trim(substr($href, 7)) !== '';
            }

            if ($scheme === 'tel') {
                return trim(substr($href, 4)) !== '';
            }

            return false;
        }

        return str_starts_with($href, '/')
            || str_starts_with($href, '#')
            || str_starts_with($href, '?');
    }

    private function normalizeRootChildren(DOMElement $root): void
    {
        $paragraph = null;

        for ($child = $root->firstChild; $child !== null; $child = $next) {
            $next = $child->nextSibling;

            if ($child instanceof DOMElement && in_array(Str::lower($child->tagName), self::BLOCK_TAGS, true)) {
                $paragraph = null;

                continue;
            }

            if ($child instanceof DOMElement && ! in_array(Str::lower($child->tagName), self::INLINE_TAGS, true)) {
                $root->removeChild($child);

                continue;
            }

            if ($paragraph === null) {
                $paragraph = $root->ownerDocument->createElement('p');
                $root->insertBefore($paragraph, $child);
            }

            $paragraph->appendChild($child);
        }
    }

    private function containsMeaningfulText(DOMElement $root): bool
    {
        $text = str_replace("\xc2\xa0", ' ', $root->textContent ?? '');

        return trim($text) !== '';
    }

    private function renameElement(DOMElement $element, string $tag): DOMElement
    {
        $replacement = $element->ownerDocument->createElement($tag);

        while ($element->firstChild) {
            $replacement->appendChild($element->firstChild);
        }

        $element->parentNode?->replaceChild($replacement, $element);

        return $replacement;
    }

    private function unwrapElement(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if (! $parent) {
            return;
        }

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    private function innerHtml(DOMElement $element): string
    {
        $html = '';

        foreach ($element->childNodes as $child) {
            $html .= $element->ownerDocument->saveHTML($child);
        }

        return $html;
    }
}
