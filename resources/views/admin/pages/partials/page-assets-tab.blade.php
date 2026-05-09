@php
    $pageAssets = $page->pageAssets->sortBy(fn ($asset) => sprintf('%010d-%010d', (int) $asset->sort_order, (int) $asset->id))->values();
    $siteHandle = $page->site?->handle ?: 'site';
    $pageSlug = $page->slug ?: 'page';
    $suggestedBase = '/site/'.$siteHandle.'/pages/'.$pageSlug.'/';
    $closeUrl = $pageAssetsTab['closeUrl'] ?? route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets']);
@endphp

<div class="wb-card wb-card-muted">
    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
        <div class="wb-stack wb-gap-1">
            <strong>Page Assets</strong>
            <span class="wb-text-sm wb-text-muted">Manage local <code>/site/...</code> CSS and JS files for this page only.</span>
        </div>

        @if ($canManagePageAssets)
            <div class="wb-cluster wb-cluster-2">
                <a href="{{ route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets', 'modal' => 'create-page-asset', 'asset_type' => 'css']) }}" class="wb-btn wb-btn-secondary" aria-haspopup="dialog">Add CSS asset</a>
                <a href="{{ route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets', 'modal' => 'create-page-asset', 'asset_type' => 'js']) }}" class="wb-btn wb-btn-secondary" aria-haspopup="dialog">Add JS asset</a>
            </div>
        @endif
    </div>

    <div class="wb-card-body wb-stack wb-gap-4">
        <div class="wb-text-sm wb-text-muted">Suggested base: <code title="{{ $suggestedBase }}">{{ $suggestedBase }}</code></div>

        @if (! $canManagePageAssets && $pageAssets->isNotEmpty())
            <div class="wb-alert wb-alert-info">Only super admins can manage page assets.</div>
        @endif

        @if ($pageAssets->isEmpty())
            <div class="wb-empty-state">
                <div class="wb-empty-title">No page assets yet.</div>
                <div class="wb-empty-text">Add CSS or JS files when this page needs page-specific styling or interaction.</div>

                @if ($canManagePageAssets)
                    <div class="wb-cluster wb-cluster-2 wb-mt-3">
                        <a href="{{ route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets', 'modal' => 'create-page-asset', 'asset_type' => 'css']) }}" class="wb-btn wb-btn-secondary" aria-haspopup="dialog">Add CSS asset</a>
                        <a href="{{ route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets', 'modal' => 'create-page-asset', 'asset_type' => 'js']) }}" class="wb-btn wb-btn-secondary" aria-haspopup="dialog">Add JS asset</a>
                    </div>
                @endif
            </div>
        @else
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped wb-table-hover">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Path</th>
                            <th>Loading</th>
                            <th>Status</th>
                            <th>Sort</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pageAssets as $pageAsset)
                            <tr>
                                <td>
                                    <span class="wb-status-pill {{ $pageAsset->type === 'js' ? 'wb-status-pending' : 'wb-status-info' }}">{{ strtoupper($pageAsset->type) }}</span>
                                </td>
                                <td>
                                    <div class="wb-stack wb-gap-1">
                                        <span title="{{ $pageAsset->path }}"><code>{{ $pageAsset->path }}</code></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="wb-cluster wb-cluster-2 wb-text-sm">
                                        <span>{{ $pageAsset->type === 'js' ? 'head (legacy body_end accepted)' : 'head' }}</span>
                                        @if ($pageAsset->type === 'js' && $pageAsset->is_defer)
                                            <span class="wb-status-pill wb-status-info">defer</span>
                                        @endif
                                        @if ($pageAsset->type === 'js' && $pageAsset->is_async)
                                            <span class="wb-status-pill wb-status-pending">async</span>
                                        @endif
                                        @if ($pageAsset->type === 'js' && $pageAsset->is_module)
                                            <span class="wb-status-pill wb-status-active">module</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="wb-status-pill {{ $pageAsset->is_enabled ? 'wb-status-active' : 'wb-status-danger' }}">{{ $pageAsset->is_enabled ? 'Enabled' : 'Disabled' }}</span>
                                </td>
                                <td>{{ $pageAsset->sort_order }}</td>
                                <td>
                                    @if ($canManagePageAssets)
                                        <div class="wb-action-group">
                                            <a href="{{ route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets', 'modal' => 'edit-page-asset', 'page_asset' => $pageAsset->id]) }}" class="wb-action-btn wb-action-btn-edit" title="Edit asset" aria-label="Edit asset" aria-haspopup="dialog">
                                                <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                                            </a>

                                            <a href="{{ route('admin.pages.edit', ['page' => $page, 'tab' => 'page-assets', 'modal' => 'delete-page-asset', 'page_asset' => $pageAsset->id]) }}" class="wb-action-btn wb-action-btn-delete" title="Delete asset" aria-label="Delete asset" aria-haspopup="dialog">
                                                <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                            </a>
                                        </div>
                                    @else
                                        <span class="wb-text-sm wb-text-muted">Read only</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
