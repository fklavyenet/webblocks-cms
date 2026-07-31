<?php

namespace WebBlocks\Cms\Support\Pages;

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
        'description' => 'Default layout, plus a narrow sticky TOC rail beside main when a TOC block is present there.',
        'is_system' => true,
        'is_active' => true,
        'sort_order' => 2,
        'body_class' => 'layout-article',
        // Reuses the default single-column shell: header/main/footer stack the
        // same way default does. The rail is not a slot region — it only
        // exists when pages.partials.slots.main finds a top-level toc block,
        // and pulls that one block into a second grid column. A page using
        // this layout without a TOC renders exactly like Default Layout.
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
