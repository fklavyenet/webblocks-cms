<?php

namespace App\Support\Blocks;

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

        $overlayRoot = null;

        foreach (iterator_to_array($root->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if ($child->getAttribute('id') === 'wb-overlay-root') {
                $overlayRoot = $child;

                continue;
            }

            if ($this->shouldMoveToBodyEnd($child)) {
                $bodyEndHtml[] = $document->saveHTML($child) ?: '';
                $child->parentNode?->removeChild($child);

                continue;
            }
        }

        $overlayHtml = null;

        if ($overlayRoot instanceof DOMElement) {
            $overlayHtml = $this->innerHtml($overlayRoot);
            $overlayRoot->parentNode?->removeChild($overlayRoot);
        }

        return [
            'content' => $this->innerHtml($root),
            'overlay' => is_string($overlayHtml) && trim($overlayHtml) !== '' ? $overlayHtml : null,
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
        $className = ' '.trim($element->getAttribute('class')).' ';

        foreach ([' wb-modal ', ' wb-drawer ', ' wb-dropdown-menu ', ' wb-popover-panel '] as $candidate) {
            if (str_contains($className, $candidate)) {
                return true;
            }
        }

        return false;
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
