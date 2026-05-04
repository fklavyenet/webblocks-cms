<?php

namespace App\Support\Formatting;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\HtmlString;

class SafeRichTextRenderer
{
    private const ROOT_MARKER = 'data-wb-rich-text-root';

    private const DANGEROUS_TAGS = [
        'button',
        'embed',
        'figure',
        'iframe',
        'img',
        'object',
        'script',
        'style',
        'table',
        'tbody',
        'td',
        'template',
        'tfoot',
        'th',
        'thead',
        'tr',
    ];

    public function render(?string $content): HtmlString
    {
        return new HtmlString($this->sanitize($content));
    }

    public function sanitize(?string $content): string
    {
        $content = trim((string) ($content ?? ''));

        if ($content === '') {
            return '';
        }

        $root = $this->parseRoot($content);

        if (! $root) {
            return '';
        }

        return $this->sanitizeRootChildren($root);
    }

    private function parseRoot(string $content): ?DOMElement
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $markup = '<!DOCTYPE html><html><body><div '.self::ROOT_MARKER.'="1">'.$content.'</div></body></html>';
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML('<?xml encoding="UTF-8">'.$markup, LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $loaded) {
            return null;
        }

        $root = (new DOMXPath($document))->query('//div[@'.self::ROOT_MARKER.'="1"]')->item(0);

        return $root instanceof DOMElement ? $root : null;
    }

    private function sanitizeRootChildren(DOMNode $parent): string
    {
        $blocks = [];
        $inlineBuffer = '';

        foreach ($this->childNodes($parent) as $child) {
            $this->consumeRootNode($child, $blocks, $inlineBuffer);
        }

        $this->flushInlineBuffer($blocks, $inlineBuffer);

        return implode('', $blocks);
    }

    private function consumeRootNode(DOMNode $node, array &$blocks, string &$inlineBuffer): void
    {
        if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
            $inlineBuffer .= $this->escapeText($node->textContent ?? '');

            return;
        }

        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return;
        }

        $tag = $this->tagName($node);

        if ($this->isDangerousTag($tag)) {
            return;
        }

        if ($tag === 'p') {
            $this->flushInlineBuffer($blocks, $inlineBuffer);

            $content = $this->sanitizeInlineChildren($node);

            if ($this->hasMeaningfulInlineContent($content)) {
                $blocks[] = '<p>'.$content.'</p>';
            }

            return;
        }

        if (in_array($tag, ['ul', 'ol'], true)) {
            $this->flushInlineBuffer($blocks, $inlineBuffer);

            $list = $this->sanitizeList($node, $tag);

            if ($list !== '') {
                $blocks[] = $list;
            }

            return;
        }

        if ($tag === 'li') {
            $this->flushInlineBuffer($blocks, $inlineBuffer);

            $item = $this->sanitizeListItem($node);

            if ($item !== '') {
                $blocks[] = '<ul>'.$item.'</ul>';
            }

            return;
        }

        if (in_array($tag, ['strong', 'em', 'code', 'a', 'br'], true)) {
            $inlineBuffer .= $this->sanitizeInlineNode($node);

            return;
        }

        foreach ($this->childNodes($node) as $child) {
            $this->consumeRootNode($child, $blocks, $inlineBuffer);
        }
    }

    private function flushInlineBuffer(array &$blocks, string &$inlineBuffer): void
    {
        if (! $this->hasMeaningfulInlineContent($inlineBuffer)) {
            $inlineBuffer = '';

            return;
        }

        $blocks[] = '<p>'.$inlineBuffer.'</p>';
        $inlineBuffer = '';
    }

    private function sanitizeList(DOMNode $node, string $tag): string
    {
        $items = [];

        foreach ($this->childNodes($node) as $child) {
            $this->collectListItems($child, $items);
        }

        return $items === [] ? '' : '<'.$tag.'>'.implode('', $items).'</'.$tag.'>';
    }

    private function collectListItems(DOMNode $node, array &$items): void
    {
        if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
            $text = $this->escapeText($node->textContent ?? '');

            if ($this->hasMeaningfulInlineContent($text)) {
                $items[] = '<li>'.$text.'</li>';
            }

            return;
        }

        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return;
        }

        $tag = $this->tagName($node);

        if ($this->isDangerousTag($tag)) {
            return;
        }

        if ($tag === 'li') {
            $item = $this->sanitizeListItem($node);

            if ($item !== '') {
                $items[] = $item;
            }

            return;
        }

        if (in_array($tag, ['ul', 'ol'], true)) {
            foreach ($this->childNodes($node) as $child) {
                $this->collectListItems($child, $items);
            }

            return;
        }

        $content = $this->sanitizeInlineChildren($node);

        if ($this->hasMeaningfulInlineContent($content)) {
            $items[] = '<li>'.$content.'</li>';

            return;
        }

        foreach ($this->childNodes($node) as $child) {
            $this->collectListItems($child, $items);
        }
    }

    private function sanitizeListItem(DOMNode $node): string
    {
        $content = $this->sanitizeInlineChildren($node);

        return $this->hasMeaningfulInlineContent($content)
            ? '<li>'.$content.'</li>'
            : '';
    }

    private function sanitizeInlineChildren(DOMNode $parent): string
    {
        $html = '';

        foreach ($this->childNodes($parent) as $child) {
            $html .= $this->sanitizeInlineNode($child);
        }

        return $html;
    }

    private function sanitizeInlineNode(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
            return $this->escapeText($node->textContent ?? '');
        }

        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return '';
        }

        $tag = $this->tagName($node);

        if ($this->isDangerousTag($tag)) {
            return '';
        }

        if (in_array($tag, ['strong', 'em', 'code'], true)) {
            $content = $this->sanitizeInlineChildren($node);

            return $this->hasMeaningfulInlineContent($content)
                ? '<'.$tag.'>'.$content.'</'.$tag.'>'
                : '';
        }

        if ($tag === 'a') {
            $content = $this->sanitizeInlineChildren($node);

            if (! $this->hasMeaningfulInlineContent($content)) {
                return '';
            }

            $href = $this->sanitizeHref($node instanceof DOMElement ? $node->getAttribute('href') : null);

            if ($href === null) {
                return $content;
            }

            $rel = $this->isExternalHref($href) ? ' rel="noopener noreferrer"' : '';

            return '<a href="'.$this->escapeAttribute($href).'"'.$rel.'>'.$content.'</a>';
        }

        if ($tag === 'br') {
            return '<br>';
        }

        return $this->sanitizeInlineChildren($node);
    }

    private function sanitizeHref(?string $href): ?string
    {
        $href = trim(html_entity_decode((string) ($href ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($href === '' || preg_match('/\s/u', $href) === 1) {
            return null;
        }

        if (str_starts_with($href, '/')) {
            return str_starts_with($href, '//') ? null : $href;
        }

        if (str_starts_with($href, '#')) {
            return mb_strlen($href) > 1 ? $href : null;
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $href) !== 1) {
            return null;
        }

        $scheme = strtolower((string) parse_url($href, PHP_URL_SCHEME));

        if (in_array($scheme, ['http', 'https'], true)) {
            return filter_var($href, FILTER_VALIDATE_URL) !== false ? $href : null;
        }

        if ($scheme === 'mailto') {
            return substr($href, strlen('mailto:')) !== '' ? $href : null;
        }

        if ($scheme === 'tel') {
            return substr($href, strlen('tel:')) !== '' ? $href : null;
        }

        return null;
    }

    private function isExternalHref(string $href): bool
    {
        return preg_match('/^https?:\/\//i', $href) === 1;
    }

    private function hasMeaningfulInlineContent(string $html): bool
    {
        $text = strip_tags(str_ireplace('<br>', '', str_ireplace('<br/>', '', str_ireplace('<br />', '', $html))));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);

        return trim($text) !== '';
    }

    private function isDangerousTag(string $tag): bool
    {
        return in_array($tag, self::DANGEROUS_TAGS, true)
            || preg_match('/^h[1-6]$/', $tag) === 1;
    }

    private function childNodes(DOMNode $parent): array
    {
        $nodes = [];

        foreach ($parent->childNodes as $child) {
            $nodes[] = $child;
        }

        return $nodes;
    }

    private function tagName(DOMNode $node): string
    {
        return strtolower($node->nodeName);
    }

    private function escapeText(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function escapeAttribute(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
