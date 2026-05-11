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

        if ($html === '' || ! str_contains($html, 'wb-overlay-root')) {
            return [
                'content' => $html,
                'overlay' => null,
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
            ];
        }

        $root = $document->getElementById('wb-trusted-html-root');

        if (! $root instanceof DOMElement) {
            return [
                'content' => $html,
                'overlay' => null,
            ];
        }

        $overlayRoot = null;

        foreach (iterator_to_array($root->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if ($child->getAttribute('id') === 'wb-overlay-root') {
                $overlayRoot = $child;

                break;
            }
        }

        if (! $overlayRoot instanceof DOMElement) {
            return [
                'content' => $html,
                'overlay' => null,
            ];
        }

        $overlayHtml = $this->innerHtml($overlayRoot);
        $overlayRoot->parentNode?->removeChild($overlayRoot);

        return [
            'content' => $this->innerHtml($root),
            'overlay' => trim($overlayHtml) !== '' ? $overlayHtml : null,
        ];
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
