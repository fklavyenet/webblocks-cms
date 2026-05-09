@php
    $pageAssets = collect(old('page_assets', $page->pageAssets?->map(fn ($asset) => [
        'type' => $asset->type,
        'path' => $asset->path,
        'sort_order' => $asset->sort_order,
        'is_enabled' => $asset->is_enabled,
        'is_defer' => $asset->is_defer,
        'is_async' => $asset->is_async,
        'is_module' => $asset->is_module,
    ])->all() ?? []));
@endphp

<div class="wb-stack wb-gap-4" data-wb-page-assets>
    <div class="wb-stack wb-gap-1">
        <strong>Page Assets</strong>
        <p class="wb-text-sm wb-text-muted wb-m-0">Page assets are advanced page-specific CSS and JS files loaded only on this public page.</p>
        <p class="wb-text-sm wb-text-muted wb-m-0">V1 accepts local <code>/site/...</code> paths only.</p>
    </div>

    @if (! $canManagePageAssets)
        <div class="wb-alert wb-alert-info">Only super admins can manage page assets in V1.</div>
    @endif

    <div class="wb-cluster wb-cluster-2">
        @if ($canManagePageAssets)
            <button type="button" class="wb-btn wb-btn-secondary" data-wb-page-assets-add="css">Add CSS asset</button>
            <button type="button" class="wb-btn wb-btn-secondary" data-wb-page-assets-add="js">Add JS asset</button>
        @endif
    </div>

    <div class="wb-stack wb-gap-3" data-wb-page-assets-list>
        @forelse ($pageAssets as $index => $pageAsset)
            <div class="wb-card wb-card-muted" data-wb-page-asset-row>
                <div class="wb-card-body">
                    <div class="wb-grid wb-grid-2 wb-gap-3">
                        <div class="wb-stack wb-gap-1">
                            <label>Type</label>
                            <input type="text" class="wb-input" value="{{ strtoupper((string) ($pageAsset['type'] ?? 'css')) }}" readonly>
                            <input type="hidden" name="page_assets[{{ $index }}][type]" value="{{ $pageAsset['type'] ?? 'css' }}" data-wb-page-asset-type>
                        </div>
                        <div class="wb-stack wb-gap-1">
                            <label>Path</label>
                            <input type="text" name="page_assets[{{ $index }}][path]" class="wb-input" value="{{ $pageAsset['path'] ?? '' }}" placeholder="/site/example/page/file.{{ ($pageAsset['type'] ?? 'css') === 'js' ? 'js' : 'css' }}" @disabled(! $canManagePageAssets)>
                        </div>
                    </div>

                    <div class="wb-grid wb-grid-4 wb-gap-3 wb-mt-3">
                        <div class="wb-stack wb-gap-1">
                            <label>Sort Order</label>
                            <input type="number" name="page_assets[{{ $index }}][sort_order]" class="wb-input" min="0" value="{{ $pageAsset['sort_order'] ?? $index }}" @disabled(! $canManagePageAssets)>
                        </div>
                        <label class="wb-cluster wb-cluster-2 wb-text-sm wb-mt-6">
                            <input type="checkbox" name="page_assets[{{ $index }}][is_enabled]" value="1" @checked((bool) ($pageAsset['is_enabled'] ?? true)) @disabled(! $canManagePageAssets)>
                            <span>Enabled</span>
                        </label>
                        <label class="wb-cluster wb-cluster-2 wb-text-sm wb-mt-6" data-wb-page-asset-js-only @if (($pageAsset['type'] ?? 'css') !== 'js') hidden @endif>
                            <input type="checkbox" name="page_assets[{{ $index }}][is_defer]" value="1" @checked((bool) ($pageAsset['is_defer'] ?? (($pageAsset['type'] ?? 'css') === 'js'))) @disabled(! $canManagePageAssets)>
                            <span>Defer</span>
                        </label>
                        <div class="wb-cluster wb-cluster-2 wb-justify-end wb-mt-5">
                            @if ($canManagePageAssets)
                                <button type="button" class="wb-btn wb-btn-secondary" data-wb-page-assets-remove>Remove row</button>
                            @endif
                        </div>
                    </div>

                    <div class="wb-grid wb-grid-3 wb-gap-3 wb-mt-3" data-wb-page-asset-js-only @if (($pageAsset['type'] ?? 'css') !== 'js') hidden @endif>
                        <label class="wb-cluster wb-cluster-2 wb-text-sm">
                            <input type="checkbox" name="page_assets[{{ $index }}][is_async]" value="1" @checked((bool) ($pageAsset['is_async'] ?? false)) @disabled(! $canManagePageAssets)>
                            <span>Async</span>
                        </label>
                        <label class="wb-cluster wb-cluster-2 wb-text-sm">
                            <input type="checkbox" name="page_assets[{{ $index }}][is_module]" value="1" @checked((bool) ($pageAsset['is_module'] ?? false)) @disabled(! $canManagePageAssets)>
                            <span>Module</span>
                        </label>
                    </div>
                </div>
            </div>
        @empty
            <div class="wb-empty-state" data-wb-page-assets-empty>
                <div class="wb-empty-title">No page assets configured</div>
                <div class="wb-empty-text">Add local <code>/site/...</code> CSS or JS files when a single public page needs its own behavior or styling.</div>
            </div>
        @endforelse
    </div>

    <template data-wb-page-asset-template="css">
        <div class="wb-card wb-card-muted" data-wb-page-asset-row>
            <div class="wb-card-body">
                <div class="wb-grid wb-grid-2 wb-gap-3">
                    <div class="wb-stack wb-gap-1">
                        <label>Type</label>
                        <input type="text" class="wb-input" value="CSS" readonly>
                        <input type="hidden" name="__NAME__[type]" value="css" data-wb-page-asset-type>
                    </div>
                    <div class="wb-stack wb-gap-1">
                        <label>Path</label>
                        <input type="text" name="__NAME__[path]" class="wb-input" placeholder="/site/example/page/file.css">
                    </div>
                </div>
                <div class="wb-grid wb-grid-4 wb-gap-3 wb-mt-3">
                    <div class="wb-stack wb-gap-1">
                        <label>Sort Order</label>
                        <input type="number" name="__NAME__[sort_order]" class="wb-input" min="0" value="0">
                    </div>
                    <label class="wb-cluster wb-cluster-2 wb-text-sm wb-mt-6">
                        <input type="checkbox" name="__NAME__[is_enabled]" value="1" checked>
                        <span>Enabled</span>
                    </label>
                    <label class="wb-cluster wb-cluster-2 wb-text-sm wb-mt-6" data-wb-page-asset-js-only hidden>
                        <input type="checkbox" name="__NAME__[is_defer]" value="1">
                        <span>Defer</span>
                    </label>
                    <div class="wb-cluster wb-cluster-2 wb-justify-end wb-mt-5">
                        <button type="button" class="wb-btn wb-btn-secondary" data-wb-page-assets-remove>Remove row</button>
                    </div>
                </div>
                <div class="wb-grid wb-grid-3 wb-gap-3 wb-mt-3" data-wb-page-asset-js-only hidden>
                    <label class="wb-cluster wb-cluster-2 wb-text-sm">
                        <input type="checkbox" name="__NAME__[is_async]" value="1">
                        <span>Async</span>
                    </label>
                    <label class="wb-cluster wb-cluster-2 wb-text-sm">
                        <input type="checkbox" name="__NAME__[is_module]" value="1">
                        <span>Module</span>
                    </label>
                </div>
            </div>
        </div>
    </template>

    <template data-wb-page-asset-template="js">
        <div class="wb-card wb-card-muted" data-wb-page-asset-row>
            <div class="wb-card-body">
                <div class="wb-grid wb-grid-2 wb-gap-3">
                    <div class="wb-stack wb-gap-1">
                        <label>Type</label>
                        <input type="text" class="wb-input" value="JS" readonly>
                        <input type="hidden" name="__NAME__[type]" value="js" data-wb-page-asset-type>
                    </div>
                    <div class="wb-stack wb-gap-1">
                        <label>Path</label>
                        <input type="text" name="__NAME__[path]" class="wb-input" placeholder="/site/example/page/file.js">
                    </div>
                </div>
                <div class="wb-grid wb-grid-4 wb-gap-3 wb-mt-3">
                    <div class="wb-stack wb-gap-1">
                        <label>Sort Order</label>
                        <input type="number" name="__NAME__[sort_order]" class="wb-input" min="0" value="0">
                    </div>
                    <label class="wb-cluster wb-cluster-2 wb-text-sm wb-mt-6">
                        <input type="checkbox" name="__NAME__[is_enabled]" value="1" checked>
                        <span>Enabled</span>
                    </label>
                    <label class="wb-cluster wb-cluster-2 wb-text-sm wb-mt-6" data-wb-page-asset-js-only>
                        <input type="checkbox" name="__NAME__[is_defer]" value="1" checked>
                        <span>Defer</span>
                    </label>
                    <div class="wb-cluster wb-cluster-2 wb-justify-end wb-mt-5">
                        <button type="button" class="wb-btn wb-btn-secondary" data-wb-page-assets-remove>Remove row</button>
                    </div>
                </div>
                <div class="wb-grid wb-grid-3 wb-gap-3 wb-mt-3" data-wb-page-asset-js-only>
                    <label class="wb-cluster wb-cluster-2 wb-text-sm">
                        <input type="checkbox" name="__NAME__[is_async]" value="1">
                        <span>Async</span>
                    </label>
                    <label class="wb-cluster wb-cluster-2 wb-text-sm">
                        <input type="checkbox" name="__NAME__[is_module]" value="1">
                        <span>Module</span>
                    </label>
                </div>
            </div>
        </div>
    </template>
</div>
