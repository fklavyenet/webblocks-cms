<?php

namespace WebBlocks\Cms\Support\Catalog;

use Illuminate\Support\Facades\Schema;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\PageLayoutSlot;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Pages\PageLayoutCatalog;

class CoreLayoutCatalogSyncer
{
    public function sync(): array
    {
        $summary = [
            'layouts' => 0,
            'slot_types' => 0,
            'layout_slots' => 0,
        ];

        $now = now();

        PageLayout::query()->upsert(
            collect(PageLayoutCatalog::definitions())
                ->map(fn (array $layout) => [
                    'handle' => $layout['handle'],
                    'name' => $layout['name'],
                    'description' => $layout['description'] ?? null,
                    'is_system' => $layout['is_system'] ?? false,
                    'is_active' => $layout['is_active'] ?? true,
                    'sort_order' => $layout['sort_order'] ?? 0,
                    'body_class' => $layout['body_class'] ?? null,
                    'shell_type' => $layout['shell_type'] ?? 'default',
                    'slot_schema' => isset($layout['slot_schema']) ? json_encode($layout['slot_schema'], JSON_UNESCAPED_SLASHES) : null,
                    'wrapper_schema' => isset($layout['wrapper_schema']) ? json_encode($layout['wrapper_schema'], JSON_UNESCAPED_SLASHES) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->all(),
            ['handle'],
            ['name', 'description', 'is_system', 'is_active', 'sort_order', 'body_class', 'shell_type', 'slot_schema', 'wrapper_schema', 'updated_at']
        );

        $summary['layouts'] = count(PageLayoutCatalog::definitions());

        if (! Schema::hasTable('page_layout_slots')) {
            return $summary;
        }

        $layouts = PageLayout::query()->get()->keyBy('handle');

        foreach (PageLayoutCatalog::definitions() as $definition) {
            $pageLayout = $layouts->get($definition['handle']);

            if (! $pageLayout) {
                continue;
            }

            foreach ($definition['managed_slots'] ?? [] as $slotDefinition) {
                $slotType = SlotType::query()->updateOrCreate(
                    ['slug' => $slotDefinition['slot_type_slug']],
                    [
                        'name' => $slotDefinition['label'] ?? str($slotDefinition['slot_type_slug'])->headline()->toString(),
                        'description' => $slotDefinition['description'] ?? null,
                        'is_system' => true,
                        'sort_order' => $slotDefinition['sort_order'] ?? 0,
                        'status' => 'published',
                    ],
                );
                $summary['slot_types']++;

                PageLayoutSlot::query()->updateOrCreate(
                    [
                        'page_layout_id' => $pageLayout->id,
                        'slot_name' => $slotDefinition['slot_name'],
                    ],
                    [
                        'slot_type_id' => $slotType->id,
                        'label' => $slotDefinition['label'] ?? null,
                        'description' => $slotDefinition['description'] ?? null,
                        'html_element' => $slotDefinition['html_element'] ?? 'div',
                        'html_id' => $slotDefinition['html_id'] ?? null,
                        'html_classes' => $slotDefinition['html_classes'] ?? null,
                        'before_html' => $slotDefinition['before_html'] ?? null,
                        'start_html' => $slotDefinition['start_html'] ?? null,
                        'end_html' => $slotDefinition['end_html'] ?? null,
                        'after_html' => $slotDefinition['after_html'] ?? null,
                        'is_required' => (bool) ($slotDefinition['is_required'] ?? false),
                        'is_active' => (bool) ($slotDefinition['is_active'] ?? true),
                        'is_system' => (bool) ($slotDefinition['is_system'] ?? false),
                        'sort_order' => (int) ($slotDefinition['sort_order'] ?? 0),
                    ],
                );
                $summary['layout_slots']++;
            }
        }

        return $summary;
    }

    public function slotSlugs(): array
    {
        return collect(PageLayoutCatalog::definitions())
            ->flatMap(fn (array $definition) => collect($definition['managed_slots'] ?? [])->pluck('slot_type_slug'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
