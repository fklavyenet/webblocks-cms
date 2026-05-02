<?php

namespace Database\Seeders;

use App\Models\LayoutType;
use App\Models\LayoutTypeSlot;
use App\Models\SlotType;
use Illuminate\Database\Seeder;

class LayoutTypeSeeder extends Seeder
{
    public function run(): void
    {
        $slotTypeMap = SlotType::query()->whereIn('slug', ['header', 'sidebar', 'main', 'footer'])->pluck('id', 'slug');

        $layouts = [
            [
                'layout' => [
                    'name' => 'Default Layout',
                    'slug' => 'default',
                    'description' => 'Default reusable page layout.',
                    'is_system' => true,
                    'sort_order' => 1,
                    'status' => 'published',
                    'settings' => ['public_shell' => 'default'],
                ],
                'slots' => [
                    ['slug' => 'header', 'ownership' => 'layout', 'wrapper_preset' => 'default', 'sort_order' => 0],
                    ['slug' => 'main', 'ownership' => 'page', 'wrapper_preset' => 'default', 'sort_order' => 1],
                    ['slug' => 'footer', 'ownership' => 'layout', 'wrapper_preset' => 'default', 'sort_order' => 2],
                ],
            ],
            [
                'layout' => [
                    'name' => 'Docs Layout',
                    'slug' => 'docs',
                    'description' => 'Shared docs shell with layout-owned header and sidebar.',
                    'is_system' => true,
                    'sort_order' => 2,
                    'status' => 'published',
                    'settings' => ['public_shell' => 'docs'],
                ],
                'slots' => [
                    ['slug' => 'header', 'ownership' => 'layout', 'wrapper_preset' => 'docs-navbar', 'sort_order' => 0],
                    ['slug' => 'sidebar', 'ownership' => 'layout', 'wrapper_preset' => 'docs-sidebar', 'sort_order' => 1],
                    ['slug' => 'main', 'ownership' => 'page', 'wrapper_preset' => 'docs-main', 'sort_order' => 2],
                    ['slug' => 'footer', 'ownership' => 'layout', 'wrapper_preset' => 'default', 'sort_order' => 3],
                ],
            ],
        ];

        foreach ($layouts as $definition) {
            $layoutType = LayoutType::query()->updateOrCreate(
                ['slug' => $definition['layout']['slug']],
                $definition['layout']
            );

            $keptSlotIds = [];

            foreach ($definition['slots'] as $slotDefinition) {
                $slotTypeId = $slotTypeMap[$slotDefinition['slug']] ?? null;

                if (! $slotTypeId) {
                    continue;
                }

                $slot = LayoutTypeSlot::query()->updateOrCreate(
                    [
                        'layout_type_id' => $layoutType->id,
                        'slot_type_id' => $slotTypeId,
                    ],
                    [
                        'sort_order' => $slotDefinition['sort_order'],
                        'ownership' => $slotDefinition['ownership'],
                        'wrapper_preset' => $slotDefinition['wrapper_preset'],
                        'wrapper_element' => match ($slotDefinition['wrapper_preset']) {
                            'docs-navbar' => 'header',
                            'docs-sidebar' => 'aside',
                            'docs-main' => 'main',
                            default => match ($slotDefinition['slug']) {
                                'header' => 'header',
                                'main' => 'main',
                                'sidebar' => 'aside',
                                'footer' => 'footer',
                                default => 'div',
                            },
                        },
                    ]
                );

                $keptSlotIds[] = $slot->id;
            }

            LayoutTypeSlot::query()
                ->where('layout_type_id', $layoutType->id)
                ->whereNotIn('id', $keptSlotIds)
                ->delete();
        }
    }
}
