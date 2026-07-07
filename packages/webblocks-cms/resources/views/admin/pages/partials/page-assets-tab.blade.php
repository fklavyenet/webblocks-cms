@php
    $pageAssets = $page->pageAssets->sortBy(fn ($asset) => sprintf('%010d-%010d', (int) $asset->sort_order, (int) $asset->id))->values();
    $siteHandle = $page->site?->handle ?: 'site';
    $pageSlug = $page->slug ?: 'page';
    $suggestedBase = '/site/'.$siteHandle.'/pages/'.$pageSlug.'/';
    $pageReturnUrl = $pageReturnUrl ?? request('return_url') ?? session('page_return_url');
    $closeUrl = $pageAssetsTab['closeUrl'] ?? route('admin.pages.edit', array_filter(['page' => $page, 'tab' => 'page-assets', 'return_url' => $pageReturnUrl]));
    $pageAssetsText = fn (string $key, array $replace = []) => __('webblocks-cms::admin.page_assets.'.$key, $replace);
@endphp

<div class="wb-card wb-card-muted">
    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
        <div class="wb-stack wb-gap-1">
            <strong>{{ $pageAssetsText('title') }}</strong>
            <span class="wb-text-sm wb-text-muted">{!! $pageAssetsText('description') !!}</span>
        </div>

        @if ($canManagePageAssets)
            <div class="wb-cluster wb-cluster-2">
                <a href="{{ route('admin.pages.edit', array_filter(['page' => $page, 'tab' => 'page-assets', 'modal' => 'create-page-asset', 'asset_type' => 'css', 'return_url' => $pageReturnUrl])) }}" class="wb-btn wb-btn-secondary" aria-haspopup="dialog">{{ $pageAssetsText('add_css') }}</a>
                <a href="{{ route('admin.pages.edit', array_filter(['page' => $page, 'tab' => 'page-assets', 'modal' => 'create-page-asset', 'asset_type' => 'js', 'return_url' => $pageReturnUrl])) }}" class="wb-btn wb-btn-secondary" aria-haspopup="dialog">{{ $pageAssetsText('add_js') }}</a>
            </div>
        @endif
    </div>

    <div class="wb-card-body wb-stack wb-gap-4">
        <div class="wb-text-sm wb-text-muted">{{ $pageAssetsText('suggested_base') }} <code title="{{ $suggestedBase }}">{{ $suggestedBase }}</code></div>

        @if (! $canManagePageAssets && $pageAssets->isNotEmpty())
            <div class="wb-alert wb-alert-info">{{ $pageAssetsText('super_admin_only') }}</div>
        @endif

        @if ($pageAssets->isEmpty())
            <div class="wb-empty-state">
                <div class="wb-empty-title">{{ $pageAssetsText('empty_title') }}</div>
                <div class="wb-empty-text">{{ $pageAssetsText('empty_text') }}</div>
            </div>
        @else
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped wb-table-hover">
                    <thead>
                        <tr>
                            <th>{{ $pageAssetsText('type') }}</th>
                            <th>{{ $pageAssetsText('path') }}</th>
                            <th>{{ $pageAssetsText('loading') }}</th>
                            <th>{{ $pageAssetsText('status') }}</th>
                            <th>{{ $pageAssetsText('sort') }}</th>
                            <th>{{ $pageAssetsText('actions') }}</th>
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
                                        <span>{{ $pageAsset->type === 'js' ? $pageAssetsText('head_legacy') : $pageAssetsText('head') }}</span>
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
                                    <span class="wb-status-pill {{ $pageAsset->is_enabled ? 'wb-status-active' : 'wb-status-danger' }}">{{ $pageAsset->is_enabled ? $pageAssetsText('enabled') : $pageAssetsText('disabled') }}</span>
                                </td>
                                <td>{{ $pageAsset->sort_order }}</td>
                                <td>
                                    @if ($canManagePageAssets)
                                        <div class="wb-action-group">
                                            <a href="{{ route('admin.pages.edit', array_filter(['page' => $page, 'tab' => 'page-assets', 'modal' => 'edit-page-asset', 'page_asset' => $pageAsset->id, 'return_url' => $pageReturnUrl])) }}" class="wb-action-btn wb-action-btn-edit" title="{{ $pageAssetsText('edit_asset') }}" aria-label="{{ $pageAssetsText('edit_asset') }}" aria-haspopup="dialog">
                                                <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                                            </a>

                                            <a href="{{ route('admin.pages.edit', array_filter(['page' => $page, 'tab' => 'page-assets', 'modal' => 'delete-page-asset', 'page_asset' => $pageAsset->id, 'return_url' => $pageReturnUrl])) }}" class="wb-action-btn wb-action-btn-delete" title="{{ $pageAssetsText('delete_asset') }}" aria-label="{{ $pageAssetsText('delete_asset') }}" aria-haspopup="dialog">
                                                <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                            </a>
                                        </div>
                                    @else
                                        <span class="wb-text-sm wb-text-muted">{{ $pageAssetsText('read_only') }}</span>
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
