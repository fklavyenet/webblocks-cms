@php
    $requestedModal = $pageAssetsTab['requestedModal'] ?? '';
    $requestedType = $pageAssetsTab['requestedType'] ?? '';
    $selectedAsset = $pageAssetsTab['selectedAsset'] ?? null;
    $pageReturnUrl = $pageReturnUrl ?? request('return_url') ?? session('page_return_url');
    $closeUrl = $pageAssetsTab['closeUrl'] ?? route('admin.pages.edit', array_filter(['page' => $page, 'tab' => 'page-assets', 'return_url' => $pageReturnUrl]));
    $siteHandle = $page->site?->handle ?: 'site';
    $pageSlug = $page->slug ?: 'page';
    $suggestedBase = '/site/'.$siteHandle.'/pages/'.$pageSlug.'/';
    $createType = in_array($requestedType, ['css', 'js'], true) ? $requestedType : 'css';
    $showCreateModal = $canManagePageAssets && $requestedModal === 'create-page-asset';
    $showEditModal = $canManagePageAssets && $requestedModal === 'edit-page-asset' && $selectedAsset;
    $showDeleteModal = $canManagePageAssets && $requestedModal === 'delete-page-asset' && $selectedAsset;
    $draftAsset = $showEditModal
        ? tap(clone $selectedAsset, function ($draft) {
            $draft->path = old('path', $draft->path);
            $draft->sort_order = old('sort_order', $draft->sort_order);
            $draft->is_enabled = old('is_enabled', $draft->is_enabled ? '1' : '0') === '1';
            $draft->is_defer = old('is_defer', $draft->is_defer ? '1' : '0') === '1';
            $draft->is_async = old('is_async', $draft->is_async ? '1' : '0') === '1';
            $draft->is_module = old('is_module', $draft->is_module ? '1' : '0') === '1';
        })
        : null;
    $createDraft = (object) [
        'type' => $createType,
        'path' => old('path', ''),
        'sort_order' => old('sort_order', 0),
        'is_enabled' => old('is_enabled', '1') === '1',
        'is_defer' => old('is_defer', $createType === 'js' ? '1' : '0') === '1',
        'is_async' => old('is_async', '0') === '1',
        'is_module' => old('is_module', '0') === '1',
    ];
@endphp

@if ($showCreateModal)
    @include('admin.pages.partials.page-assets-modal-form', [
        'modalId' => 'page-asset-create-modal',
        'modalTitle' => 'Add '.strtoupper($createDraft->type).' Asset',
        'modalDescription' => 'Add a page-specific '.strtoupper($createDraft->type).' file reference for this public page.',
        'formAction' => route('admin.pages.assets.store', ['page' => $page, 'type' => $createDraft->type]),
        'formMethod' => 'POST',
        'asset' => $createDraft,
        'closeUrl' => $closeUrl,
        'suggestedBase' => $suggestedBase,
        'modalKey' => 'create-page-asset',
        'submitLabel' => 'Save asset',
    ])
@endif

@if ($showEditModal && $draftAsset)
    @include('admin.pages.partials.page-assets-modal-form', [
        'modalId' => 'page-asset-edit-modal-'.$draftAsset->id,
        'modalTitle' => 'Edit '.strtoupper($draftAsset->type).' Asset',
        'modalDescription' => 'Update the selected page asset settings.',
        'formAction' => route('admin.pages.assets.update', ['page' => $page, 'page_asset' => $draftAsset]),
        'formMethod' => 'PUT',
        'asset' => $draftAsset,
        'closeUrl' => $closeUrl,
        'suggestedBase' => $suggestedBase,
        'modalKey' => 'edit-page-asset',
        'submitLabel' => 'Save asset',
    ])
@endif

@if ($showDeleteModal && $selectedAsset)
    @php
        $deleteModalId = 'page-asset-delete-modal-'.$selectedAsset->id;
        $deleteModalTitleId = $deleteModalId.'Title';
        $deleteModalDescriptionId = $deleteModalId.'Description';
    @endphp
    <div class="wb-overlay-layer wb-overlay-layer--dialog">
        <div class="wb-overlay-backdrop"></div>

        <div class="wb-modal wb-modal-lg is-open" id="{{ $deleteModalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $deleteModalTitleId }}" aria-describedby="{{ $deleteModalDescriptionId }}">
            <div class="wb-modal-dialog">
                <div class="wb-modal-header">
                    <div class="wb-stack wb-gap-1">
                        <h2 class="wb-modal-title" id="{{ $deleteModalTitleId }}">Delete {{ strtoupper($selectedAsset->type) }} Asset</h2>
                        <span class="wb-text-sm wb-text-muted" id="{{ $deleteModalDescriptionId }}">Confirm whether this page asset should be removed.</span>
                    </div>

                    <a href="{{ $closeUrl }}" class="wb-modal-close" aria-label="Close delete page asset modal">
                        <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                    </a>
                </div>

                <form method="POST" action="{{ route('admin.pages.assets.destroy', ['page' => $page, 'page_asset' => $selectedAsset]) }}">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="_page_asset_close_url" value="{{ $closeUrl }}">

                    <div class="wb-modal-body wb-stack wb-gap-4">
                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-body wb-stack wb-gap-2">
                                <div class="wb-cluster wb-cluster-2">
                                    <span class="wb-status-pill {{ $selectedAsset->type === 'js' ? 'wb-status-pending' : 'wb-status-info' }}">{{ strtoupper($selectedAsset->type) }}</span>
                                    <span class="wb-status-pill {{ $selectedAsset->is_enabled ? 'wb-status-active' : 'wb-status-danger' }}">{{ $selectedAsset->is_enabled ? 'Enabled' : 'Disabled' }}</span>
                                </div>
                                <strong title="{{ $selectedAsset->path }}"><code>{{ $selectedAsset->path }}</code></strong>
                                <div class="wb-text-sm wb-text-muted">Sort order: {{ $selectedAsset->sort_order }}</div>
                            </div>
                        </div>
                    </div>

                    <x-admin.form-actions
                        :cancel-url="$closeUrl"
                        :show-submit="false"
                        :delete-submit="true"
                        delete-label="Delete asset"
                        container-class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap"
                    />
                </form>
            </div>
        </div>
    </div>
@endif
