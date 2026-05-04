<?php

namespace App\Support\Formatting;

use Illuminate\Support\HtmlString;

class InlineRichTextRenderer
{
    public function render(?string $content): HtmlString
    {
        $content = (string) ($content ?? '');

        if ($content === '') {
            return new HtmlString('');
        }

        $segments = preg_split('/`([^`]+)`/', $content, -1, PREG_SPLIT_DELIM_CAPTURE);

        if (! is_array($segments)) {
            return new HtmlString(e($content));
        }

        $html = '';

        foreach ($segments as $index => $segment) {
            if ($index % 2 === 1) {
                $html .= '<code>'.e($segment).'</code>';

                continue;
            }

            $html .= e($segment);
        }

        return new HtmlString($html);
    }
}
