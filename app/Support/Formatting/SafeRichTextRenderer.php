<?php

namespace App\Support\Formatting;

use Illuminate\Support\HtmlString;

class SafeRichTextRenderer
{
    public function render(?string $content): HtmlString
    {
        $content = str_replace(["\r\n", "\r"], "\n", (string) ($content ?? ''));

        if (trim($content) === '') {
            return new HtmlString('');
        }

        $blocks = [];
        $paragraphLines = [];
        $listType = null;
        $listItems = [];

        foreach (explode("\n", trim($content)) as $line) {
            $trimmedLine = trim($line);

            if ($trimmedLine === '') {
                $this->flushParagraph($blocks, $paragraphLines);
                $this->flushList($blocks, $listType, $listItems);

                continue;
            }

            if (preg_match('/^- (.*)$/u', $trimmedLine, $matches) === 1) {
                $this->flushParagraph($blocks, $paragraphLines);

                if ($listType !== 'ul') {
                    $this->flushList($blocks, $listType, $listItems);
                    $listType = 'ul';
                }

                $listItems[] = $matches[1];

                continue;
            }

            if (preg_match('/^\d+\. (.*)$/u', $trimmedLine, $matches) === 1) {
                $this->flushParagraph($blocks, $paragraphLines);

                if ($listType !== 'ol') {
                    $this->flushList($blocks, $listType, $listItems);
                    $listType = 'ol';
                }

                $listItems[] = $matches[1];

                continue;
            }

            $this->flushList($blocks, $listType, $listItems);
            $paragraphLines[] = $trimmedLine;
        }

        $this->flushParagraph($blocks, $paragraphLines);
        $this->flushList($blocks, $listType, $listItems);

        return new HtmlString(implode('', $blocks));
    }

    private function flushParagraph(array &$blocks, array &$paragraphLines): void
    {
        if ($paragraphLines === []) {
            return;
        }

        $blocks[] = '<p>'.$this->renderInline(implode(' ', $paragraphLines)).'</p>';
        $paragraphLines = [];
    }

    private function flushList(array &$blocks, ?string &$listType, array &$listItems): void
    {
        if ($listType === null || $listItems === []) {
            $listType = null;
            $listItems = [];

            return;
        }

        $itemsHtml = '';

        foreach ($listItems as $item) {
            $itemsHtml .= '<li>'.$this->renderInline($item).'</li>';
        }

        $blocks[] = '<'.$listType.'>'.$itemsHtml.'</'.$listType.'>';
        $listType = null;
        $listItems = [];
    }

    private function renderInline(string $text): string
    {
        $html = '';
        $length = mb_strlen($text);
        $offset = 0;

        while ($offset < $length) {
            $remaining = mb_substr($text, $offset);

            if (preg_match('/^\[([^\]\n]+)\]\(([^)\n]+)\)/u', $remaining, $matches) === 1) {
                $html .= $this->renderLink($matches[0], $matches[1], $matches[2]);
                $offset += mb_strlen($matches[0]);

                continue;
            }

            if (str_starts_with($remaining, '`')) {
                $closingOffset = mb_strpos($text, '`', $offset + 1);

                if ($closingOffset !== false) {
                    $html .= '<code>'.e(mb_substr($text, $offset + 1, $closingOffset - $offset - 1)).'</code>';
                    $offset = $closingOffset + 1;

                    continue;
                }
            }

            if (str_starts_with($remaining, '**')) {
                $closingOffset = mb_strpos($text, '**', $offset + 2);

                if ($closingOffset !== false && $closingOffset > $offset + 2) {
                    $html .= '<strong>'.$this->renderInline(mb_substr($text, $offset + 2, $closingOffset - $offset - 2)).'</strong>';
                    $offset = $closingOffset + 2;

                    continue;
                }
            }

            if (mb_substr($text, $offset, 1) === '*') {
                $closingOffset = $this->findClosingItalicDelimiter($text, $offset + 1);

                if ($closingOffset !== null) {
                    $html .= '<em>'.$this->renderInline(mb_substr($text, $offset + 1, $closingOffset - $offset - 1)).'</em>';
                    $offset = $closingOffset + 1;

                    continue;
                }
            }

            $html .= e(mb_substr($text, $offset, 1));
            $offset++;
        }

        return $html;
    }

    private function findClosingItalicDelimiter(string $text, int $offset): ?int
    {
        $length = mb_strlen($text);

        while ($offset < $length) {
            if (mb_substr($text, $offset, 1) !== '*') {
                $offset++;

                continue;
            }

            if (mb_substr($text, $offset - 1, 1) === '*' || mb_substr($text, $offset + 1, 1) === '*') {
                $offset++;

                continue;
            }

            return $offset;
        }

        return null;
    }

    private function renderLink(string $fullMatch, string $label, string $href): string
    {
        if (! $this->isSafeHref($href)) {
            return e($fullMatch);
        }

        return '<a href="'.e($href).'" rel="noopener noreferrer">'.e($label).'</a>';
    }

    private function isSafeHref(string $href): bool
    {
        if (str_starts_with($href, '/') || str_starts_with($href, '#')) {
            return true;
        }

        $scheme = parse_url($href, PHP_URL_SCHEME);

        if (! is_string($scheme)) {
            return false;
        }

        return in_array(strtolower($scheme), ['http', 'https'], true);
    }
}
