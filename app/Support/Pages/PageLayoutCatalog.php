<?php

namespace App\Support\Pages;

class PageLayoutCatalog
{
    public static function definitions(): array
    {
        return [
            [
                'handle' => 'default',
                'name' => 'Default Layout',
                'description' => 'Standard public page layout.',
                'is_system' => true,
                'is_active' => true,
                'sort_order' => 0,
                'shell_type' => 'default',
                'slot_schema' => ['header', 'main', 'sidebar', 'footer'],
                'wrapper_schema' => [
                    'mode' => 'default',
                    'regions' => ['header', 'main', 'sidebar', 'footer'],
                ],
            ],
            [
                'handle' => 'docs',
                'name' => 'Docs Layout',
                'description' => 'Documentation layout with header, sidebar, and main regions.',
                'is_system' => true,
                'is_active' => true,
                'sort_order' => 1,
                'shell_type' => 'docs',
                'slot_schema' => ['header', 'sidebar', 'main', 'footer'],
                'wrapper_schema' => [
                    'mode' => 'docs',
                    'regions' => ['header', 'sidebar', 'main', 'footer'],
                ],
            ],
        ];
    }

    public static function fallback(string $handle): ?array
    {
        foreach (self::definitions() as $definition) {
            if ($definition['handle'] === $handle) {
                return $definition;
            }
        }

        return null;
    }

    public static function handles(): array
    {
        return array_values(array_map(fn (array $definition) => $definition['handle'], self::definitions()));
    }
}
