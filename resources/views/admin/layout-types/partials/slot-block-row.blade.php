@include('admin.pages.partials.slot-block-row', [
    'block' => $block,
    'parentBlock' => $parentBlock,
    'depth' => $depth,
    'page' => new \App\Models\Page,
    'slot' => $slot,
    'slotBlockRoute' => $slotBlockRoute,
    'slotBlockBaseRoute' => $slotBlockBaseRoute,
    'activeLocale' => $activeLocale,
    'expandedBlockIds' => $expandedBlockIds,
])
