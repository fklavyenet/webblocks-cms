<?php

namespace WebBlocks\Cms\Support\Sites;

use Illuminate\Support\Str;

class SiteHandle
{
    public static function normalize(mixed $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $ascii = Str::ascii($value);
        $normalized = preg_replace('/[^A-Za-z0-9]+/', '-', $ascii);

        if (! is_string($normalized)) {
            return '';
        }

        return strtolower(trim($normalized, '-'));
    }

    public static function validationPattern(): string
    {
        return '/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/';
    }
}
