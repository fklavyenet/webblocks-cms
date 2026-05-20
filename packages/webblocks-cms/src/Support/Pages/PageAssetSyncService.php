<?php

namespace WebBlocks\Cms\Support\Pages;

use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageAsset;

class PageAssetSyncService
{
    public function __construct(
        private readonly PageAssetPathValidator $pathValidator,
    ) {}

    public function sync(Page $page, array $rows): void
    {
        $page->pageAssets()->delete();

        foreach (array_values($rows) as $index => $row) {
            $type = $this->pathValidator->normalizeType($row['type'] ?? null);
            $path = $this->pathValidator->normalizeForStorage($type, $row['path'] ?? '');

            $page->pageAssets()->create([
                'type' => $type,
                'path' => $path,
                'load_position' => PageAsset::defaultLoadPositionFor($type),
                'is_defer' => $type === PageAsset::TYPE_JS ? (bool) ($row['is_defer'] ?? true) : false,
                'is_async' => $type === PageAsset::TYPE_JS ? (bool) ($row['is_async'] ?? false) : false,
                'is_module' => $type === PageAsset::TYPE_JS ? (bool) ($row['is_module'] ?? false) : false,
                'is_enabled' => (bool) ($row['is_enabled'] ?? true),
                'sort_order' => isset($row['sort_order']) ? max((int) $row['sort_order'], 0) : $index,
            ]);
        }
    }
}
