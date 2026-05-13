<?php

namespace Database\Seeders;

use App\Models\PageLayout;
use App\Support\Pages\PageLayoutCatalog;
use Illuminate\Database\Seeder;

class PageLayoutSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        PageLayout::query()->upsert(
            collect(PageLayoutCatalog::definitions())
                ->map(fn (array $layout) => [
                    ...$layout,
                    'slot_schema' => isset($layout['slot_schema']) ? json_encode($layout['slot_schema'], JSON_UNESCAPED_SLASHES) : null,
                    'wrapper_schema' => isset($layout['wrapper_schema']) ? json_encode($layout['wrapper_schema'], JSON_UNESCAPED_SLASHES) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all(),
            ['handle'],
            ['name', 'description', 'is_system', 'is_active', 'sort_order', 'shell_type', 'slot_schema', 'wrapper_schema', 'updated_at']
        );
    }
}
