<?php

namespace App\Support\Pages;

class LayoutMarkup
{
    private const SAFE_TOKEN_PATTERN = '/^[A-Za-z0-9_-]+(?:\s+[A-Za-z0-9_-]+)*$/';

    private const SAFE_ID_PATTERN = '/^[A-Za-z][A-Za-z0-9_-]*$/';

    private const SAFE_SLOT_NAME_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public static function allowedElements(): array
    {
        return ['div', 'header', 'main', 'aside', 'footer', 'section', 'nav'];
    }

    public static function normalizeTokenList(mixed $value): ?string
    {
        $value = preg_replace('/\s+/', ' ', trim((string) $value));

        return $value !== '' ? $value : null;
    }

    public static function hasValidTokenList(mixed $value): bool
    {
        $value = self::normalizeTokenList($value);

        return $value === null || preg_match(self::SAFE_TOKEN_PATTERN, $value) === 1;
    }

    public static function normalizeHtmlId(mixed $value): ?string
    {
        return self::normalizeTokenList($value);
    }

    public static function hasValidHtmlId(mixed $value): bool
    {
        $value = self::normalizeHtmlId($value);

        return $value === null || preg_match(self::SAFE_ID_PATTERN, $value) === 1;
    }

    public static function normalizeSlotName(mixed $value): ?string
    {
        $value = strtolower(trim((string) $value));

        return $value !== '' ? $value : null;
    }

    public static function hasValidSlotName(mixed $value): bool
    {
        $value = self::normalizeSlotName($value);

        return $value !== null && preg_match(self::SAFE_SLOT_NAME_PATTERN, $value) === 1;
    }

    public static function normalizeElement(mixed $value): string
    {
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, self::allowedElements(), true) ? $normalized : 'div';
    }

    public static function trustedHtmlError(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/<\s*\/?\s*(script|iframe|object|embed)\b/i', $value) === 1) {
            return 'Trusted layout HTML cannot include script, iframe, object, or embed tags.';
        }

        if (preg_match('/\son[a-z0-9_-]+\s*=/i', $value) === 1) {
            return 'Trusted layout HTML cannot include inline event handler attributes.';
        }

        if (preg_match('/javascript\s*:/i', $value) === 1) {
            return 'Trusted layout HTML cannot include javascript: URLs.';
        }

        return null;
    }
}
