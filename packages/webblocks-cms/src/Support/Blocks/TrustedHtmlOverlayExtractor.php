<?php

namespace WebBlocks\Cms\Support\Blocks;

use DOMDocument;
use DOMElement;
use DOMNode;

class TrustedHtmlOverlayExtractor
{
    public function extract(string $html): array
    {
        $html = trim($html);

        if ($html === '' || ! $this->mightContainDetachedMarkup($html)) {
            return [
                'content' => $html,
                'overlay' => null,
                'body_end' => [],
            ];
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $document->loadHTML('<?xml encoding="UTF-8"><div id="wb-trusted-html-root">'.$html.'</div>', LIBXML_NOERROR | LIBXML_NOWARNING);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $loaded) {
            return [
                'content' => $html,
                'overlay' => null,
                'body_end' => [],
            ];
        }

        $root = $document->getElementById('wb-trusted-html-root');

        if (! $root instanceof DOMElement) {
            return [
                'content' => $html,
                'overlay' => null,
                'body_end' => [],
            ];
        }

        $bodyEndHtml = [];
        $overlayHtml = [];

        foreach ($this->detachedElements($root) as $element) {
            if ($element->getAttribute('id') === 'wb-overlay-root') {
                $overlayHtml[] = $this->innerHtml($element);
                $element->parentNode?->removeChild($element);

                continue;
            }

            if ($this->shouldMoveToOverlayRoot($element)) {
                $overlayHtml[] = $document->saveHTML($element) ?: '';
                $element->parentNode?->removeChild($element);

                continue;
            }

            if ($this->shouldMoveToBodyEnd($element)) {
                $bodyEndHtml[] = $document->saveHTML($element) ?: '';
                $element->parentNode?->removeChild($element);
            }
        }

        return [
            'content' => $this->innerHtml($root),
            'overlay' => ($overlay = trim(implode('', array_filter($overlayHtml)))) !== '' ? $overlay : null,
            'body_end' => array_values(array_filter(array_map('trim', $bodyEndHtml))),
        ];
    }

    private function mightContainDetachedMarkup(string $html): bool
    {
        foreach (['wb-overlay-root', 'wb-modal', 'wb-drawer', 'wb-dropdown-menu', 'wb-popover-panel'] as $needle) {
            if (str_contains($html, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function shouldMoveToBodyEnd(DOMElement $element): bool
    {
        if ($this->shouldMoveToOverlayRoot($element)) {
            return false;
        }

        $className = ' '.trim($element->getAttribute('class')).' ';

        foreach ([' wb-toast ', ' wb-tooltip-content '] as $candidate) {
            if (str_contains($className, $candidate)) {
                return true;
            }
        }

        return false;
    }

    private function shouldMoveToOverlayRoot(DOMElement $element): bool
    {
        $className = ' '.trim($element->getAttribute('class')).' ';

        foreach ([' wb-modal ', ' wb-drawer ', ' wb-dropdown-menu ', ' wb-popover-panel '] as $candidate) {
            if (str_contains($className, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, DOMElement>
     */
    private function detachedElements(DOMElement $root): array
    {
        $detached = [];

        foreach (iterator_to_array($root->getElementsByTagName('*')) as $element) {
            if (! $element instanceof DOMElement) {
                continue;
            }

            if ($element->getAttribute('id') === 'wb-overlay-root' || $this->shouldMoveToOverlayRoot($element) || $this->shouldMoveToBodyEnd($element)) {
                $detached[] = $element;
            }
        }

        usort($detached, fn (DOMElement $left, DOMElement $right) => $this->nodeDepth($right) <=> $this->nodeDepth($left));

        return $detached;
    }

    private function nodeDepth(DOMElement $element): int
    {
        $depth = 0;
        $current = $element->parentNode;

        while ($current instanceof DOMNode) {
            $depth++;
            $current = $current->parentNode;
        }

        return $depth;
    }

    private function innerHtml(DOMNode $node): string
    {
        $html = '';

        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument?->saveHTML($child) ?? '';
        }

        return $html;
    }
}
