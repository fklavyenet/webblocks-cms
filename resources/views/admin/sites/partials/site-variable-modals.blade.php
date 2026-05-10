@php
    $requestedModal = $siteVariablesUi['requestedModal'] ?? '';
    $selectedVariable = $siteVariablesUi['selectedVariable'] ?? null;
    $closeUrl = $siteVariablesUi['closeUrl'] ?? route('admin.sites.edit', ['site' => $site, 'tab' => 'variables']);
    $showCreateModal = $canManageSiteSettings && $requestedModal === 'create-variable';
    $showEditModal = $canManageSiteSettings && $requestedModal === 'edit-variable' && $selectedVariable;
    $showDeleteModal = $canManageSiteSettings && $requestedModal === 'delete-variable' && $selectedVariable;
    $createDraft = (object) [
        'id' => null,
        'key' => old('key', ''),
        'label' => old('label', ''),
        'value' => old('value', ''),
        'sort_order' => old('sort_order', 0),
        'is_enabled' => old('is_enabled', '1') === '1',
    ];
    $editDraft = $showEditModal
        ? (object) [
            'id' => $selectedVariable->id,
            'key' => old('key', $selectedVariable->key),
            'label' => old('label', $selectedVariable->label),
            'value' => old('value', $selectedVariable->value),
            'sort_order' => old('sort_order', $selectedVariable->sort_order),
            'is_enabled' => old('is_enabled', $selectedVariable->is_enabled ? '1' : '0') === '1',
        ]
        : null;
@endphp

@if ($showCreateModal)
    @include('admin.sites.partials.site-variable-modal-form', [
        'modalId' => 'site-variable-create-modal',
        'modalTitle' => 'Add Site Variable',
        'modalDescription' => 'Create a reusable public token for this site.',
        'formAction' => route('admin.sites.variables.store', $site),
        'formMethod' => 'POST',
        'siteVariable' => $createDraft,
        'closeUrl' => $closeUrl,
        'modalKey' => 'create-variable',
        'submitLabel' => 'Save variable',
    ])
@endif

@if ($showEditModal && $editDraft)
    @include('admin.sites.partials.site-variable-modal-form', [
        'modalId' => 'site-variable-edit-modal-'.$editDraft->id,
        'modalTitle' => 'Edit Site Variable',
        'modalDescription' => 'Update the selected site variable.',
        'formAction' => route('admin.sites.variables.update', ['site' => $site, 'site_variable' => $selectedVariable]),
        'formMethod' => 'PUT',
        'siteVariable' => $editDraft,
        'closeUrl' => $closeUrl,
        'modalKey' => 'edit-variable',
        'submitLabel' => 'Save variable',
    ])
@endif

@if ($showDeleteModal && $selectedVariable)
    @php
        $deleteModalId = 'site-variable-delete-modal-'.$selectedVariable->id;
        $deleteModalTitleId = $deleteModalId.'Title';
        $deleteModalDescriptionId = $deleteModalId.'Description';
        $deleteToken = '{{ site.'.$selectedVariable->key.' }}';
    @endphp
    <div class="wb-overlay-layer wb-overlay-layer--dialog">
        <div class="wb-overlay-backdrop"></div>

        <div class="wb-modal wb-modal-lg is-open" id="{{ $deleteModalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $deleteModalTitleId }}" aria-describedby="{{ $deleteModalDescriptionId }}">
            <div class="wb-modal-dialog">
                <div class="wb-modal-header">
                    <div class="wb-stack wb-gap-1">
                        <h2 class="wb-modal-title" id="{{ $deleteModalTitleId }}">Delete Site Variable</h2>
                        <span class="wb-text-sm wb-text-muted" id="{{ $deleteModalDescriptionId }}">Confirm whether this site variable should be removed.</span>
                    </div>

                    <a href="{{ $closeUrl }}" class="wb-modal-close" aria-label="Close delete site variable modal">
                        <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                    </a>
                </div>

                <form method="POST" action="{{ route('admin.sites.variables.destroy', ['site' => $site, 'site_variable' => $selectedVariable]) }}">
                    @csrf
                    @method('DELETE')

                    <div class="wb-modal-body wb-stack wb-gap-4">
                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-body wb-stack wb-gap-2">
                                <strong>{{ $selectedVariable->displayLabel() }}</strong>
                                <div><code>{{ $deleteToken }}</code></div>
                                <div class="wb-text-sm wb-text-muted">{{ str($selectedVariable->value ?? '')->limit(160) }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="wb-modal-footer wb-flex wb-items-center wb-gap-3 wb-flex-wrap">
                        <button type="submit" class="wb-btn wb-btn-danger">Delete variable</button>
                        <a href="{{ $closeUrl }}" class="wb-btn wb-btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endif
