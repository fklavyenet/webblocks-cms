<?php

namespace WebBlocks\Cms\Support\Pages;

class PageLayoutCatalog
{
  public const COLUMN_SIDEBARS = ['sidebar-left', 'sidebar-right'];

  public static function autoAddsSlot(string $layout, ?string $slug): bool
  {
    return ! (($layout === 'default' && $slug === 'sidebar') || in_array($slug, self::COLUMN_SIDEBARS, true));
  }

  public static function allowsSlot(string $layout, ?string $slug): bool
  {
    return ! in_array($slug, self::unavailableSlots($layout), true);
  }

  public static function unavailableSlots(string $layout): array
  {
    return $layout === 'columns' ? ['sidebar'] : self::COLUMN_SIDEBARS;
  }

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
        'body_class' => 'layout-default',
        'shell_type' => 'default',
        'slot_schema' => ['header', 'main', 'sidebar', 'footer'],
        'wrapper_schema' => [
          'mode' => 'default',
          'regions' => ['header', 'main', 'sidebar', 'footer'],
        ],
        'managed_slots' => [
          [
            'slot_type_slug' => 'header',
            'slot_name' => 'header',
            'label' => 'Header',
            'html_element' => 'header',
            'is_required' => false,
            'is_active' => true,
            'is_system' => true,
            'sort_order' => 10,
          ],
          [
            'slot_type_slug' => 'main',
            'slot_name' => 'main',
            'label' => 'Main',
            'html_element' => 'main',
            'html_id' => 'main-content',
            'css_classes' => 'wb-public-main',
            'is_required' => true,
            'is_active' => true,
            'is_system' => true,
            'sort_order' => 20,
          ],
          [
            'slot_type_slug' => 'sidebar',
            'slot_name' => 'sidebar',
            'label' => 'Sidebar',
            'html_element' => 'aside',
            'is_required' => false,
            'is_active' => true,
            'is_system' => true,
            'sort_order' => 30,
          ],
          [
            'slot_type_slug' => 'footer',
            'slot_name' => 'footer',
            'label' => 'Footer',
            'html_element' => 'footer',
            'is_required' => false,
            'is_active' => true,
            'is_system' => true,
            'sort_order' => 40,
          ],
        ],
      ],
      [
        'handle' => 'docs',
        'name' => 'Docs Layout',
        'description' => 'Documentation layout with header, sidebar, and main regions.',
        'is_system' => true,
        'is_active' => true,
        'sort_order' => 1,
        'body_class' => 'layout-docs',
        'shell_type' => 'docs',
        'slot_schema' => ['header', 'sidebar', 'main', 'footer'],
        'wrapper_schema' => [
          'mode' => 'docs',
          'regions' => ['header', 'sidebar', 'main', 'footer'],
        ],
        'managed_slots' => [
          [
            'slot_type_slug' => 'header',
            'slot_name' => 'header',
            'label' => 'Header',
            'html_element' => 'nav',
            'css_classes' => 'wb-navbar wb-navbar-glass wb-w-full',
            'is_required' => false,
            'is_active' => true,
            'is_system' => true,
            'sort_order' => 10,
          ],
          [
            'slot_type_slug' => 'sidebar',
            'slot_name' => 'sidebar',
            'label' => 'Sidebar',
            'html_element' => 'aside',
            'html_id' => 'docsSidebar',
            'css_classes' => 'wb-sidebar',
            'is_required' => false,
            'is_active' => true,
            'is_system' => true,
            'sort_order' => 20,
          ],
          [
            'slot_type_slug' => 'main',
            'slot_name' => 'main',
            'label' => 'Main',
            'html_element' => 'main',
            'html_id' => 'main-content',
            'css_classes' => 'wb-dashboard-main',
            'is_required' => true,
            'is_active' => true,
            'is_system' => true,
            'sort_order' => 30,
          ],
          [
            'slot_type_slug' => 'footer',
            'slot_name' => 'footer',
            'label' => 'Footer',
            'html_element' => 'footer',
            'is_required' => false,
            'is_active' => true,
            'is_system' => true,
            'sort_order' => 40,
          ],
        ],
      ],
      [
        'handle' => 'article',
        'name' => 'Article Layout',
        'description' => 'An article-oriented default shell. TOC blocks control their own left/right placement within their slot.',
        'is_system' => true,
        'is_active' => false,
        'sort_order' => 2,
        'body_class' => 'layout-article',
        // TOC placement is handled within each content slot, independently
        // of this shell. Without a TOC, Article remains a single column.
        'shell_type' => 'default',
        'slot_schema' => ['header', 'main', 'footer'],
        'wrapper_schema' => [
          'mode' => 'default',
          'regions' => ['header', 'main', 'footer'],
        ],
        'managed_slots' => [
          [
            'slot_type_slug' => 'header',
            'slot_name' => 'header',
            'label' => 'Header',
            'html_element' => 'header',
            'is_required' => false,
            'is_active' => true,
            'is_system' => true,
            'sort_order' => 10,
          ],
          [
            'slot_type_slug' => 'main',
            'slot_name' => 'main',
            'label' => 'Main',
            'html_element' => 'main',
            'html_id' => 'main-content',
            'css_classes' => 'wb-public-main',
            'is_required' => true,
            'is_active' => true,
            'is_system' => true,
            'sort_order' => 20,
          ],
          [
            'slot_type_slug' => 'footer',
            'slot_name' => 'footer',
            'label' => 'Footer',
            'html_element' => 'footer',
            'is_required' => false,
            'is_active' => true,
            'is_system' => true,
            'sort_order' => 30,
          ],
        ],
      ],
      [
        'handle' => 'columns',
        'name' => 'Columns',
        'description' => 'Full-width header and footer with optional sidebars beside the main content.',
        'is_system' => true,
        'is_active' => true,
        'sort_order' => 3,
        'body_class' => 'layout-columns',
        'shell_type' => 'columns',
        'slot_schema' => ['header', 'sidebar-left', 'main', 'sidebar-right', 'footer'],
        'wrapper_schema' => [
          'mode' => 'columns',
          'regions' => ['header', 'sidebar-left', 'main', 'sidebar-right', 'footer'],
        ],
        'managed_slots' => [
          self::columnSlot('header', 'Header', 'header', 10),
          self::columnSlot('sidebar-left', 'Left Sidebar', 'aside', 20),
          self::columnSlot('main', 'Main', 'main', 30) + ['html_id' => 'main-content', 'css_classes' => 'wb-public-main'],
          self::columnSlot('sidebar-right', 'Right Sidebar', 'aside', 40),
          self::columnSlot('footer', 'Footer', 'footer', 50),
        ],
      ],
    ];
  }

  private static function columnSlot(string $slug, string $label, string $element, int $order): array
  {
    return [
      'slot_type_slug' => $slug,
      'slot_name' => $slug,
      'label' => $label,
      'html_element' => $element,
      'is_required' => $slug === 'main',
      'is_active' => true,
      'is_system' => true,
      'sort_order' => $order,
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
