<?php

namespace WebBlocks\Cms\Database\Seeders;

use App\Models\IconCatalogItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class IconCatalogSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->fallbackNavigationIcons() as $index => $slug) {
            IconCatalogItem::query()->updateOrCreate(
                ['source' => 'webblocks-ui', 'slug' => $slug],
                [
                    'label' => Str::of($slug)->replace('-', ' ')->title()->toString(),
                    'css_class' => 'wb-icon-'.$slug,
                    'categories' => ['navigation'],
                    'contexts' => ['navigation'],
                    'keywords' => IconCatalogItem::normalizeKeywords([$slug, 'navigation']),
                    'is_active' => true,
                    'sort_order' => $index + 1,
                ],
            );
        }
    }

    public function fallbackNavigationIcons(): array
    {
        return [
            'home',
            'rocket',
            'layers',
            'palette',
            'layout',
            'box',
            'star',
            'layout-grid',
            'circle-dot',
            'layout-dashboard',
            'settings',
            'shield-check',
            'file-text',
            'route',
            'images',
            'cookie',
            'megaphone',
            'wrench',
            'terminal',
            'code',
        ];
    }
}
