@php
    $contract = $contract ?? null;
    $modalId = 'blockTypeContractModal-'.$blockType->id;
    $modalTitleId = $modalId.'Title';
    $modalDescriptionId = $modalId.'Description';
    $isOpen = request('modal') === 'block-type-contract' && (int) request('contract_block_type') === $blockType->id;
    $contractStatusClass = match ($contract?->currentContractStatus) {
        'clear' => 'wb-status-active',
        'mostly clear', 'transitional' => 'wb-status-info',
        'needs review', 'legacy/fallback', 'not documented' => 'wb-status-pending',
        default => 'wb-status-info',
    };
@endphp

<div class="wb-overlay-layer wb-overlay-layer--dialog" @if (! $isOpen) hidden @endif>
    <div class="wb-overlay-backdrop"></div>

    <div class="wb-modal wb-modal-xl {{ $isOpen ? 'is-open' : '' }}" id="{{ $modalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $modalTitleId }}" aria-describedby="{{ $modalDescriptionId }}" data-admin-block-type-contract-modal>
        <div class="wb-modal-dialog">
            <div class="wb-modal-header">
                <div class="wb-stack wb-gap-1">
                    <h2 class="wb-modal-title" id="{{ $modalTitleId }}">Block Type Contract: {{ $blockType->name }}</h2>
                    <span class="wb-text-sm wb-text-muted" id="{{ $modalDescriptionId }}">Review the current shipped block type contract. This modal is informational only and does not save changes.</span>
                </div>

                <a href="{{ $closeUrl }}" class="wb-modal-close" aria-label="Close block type contract modal">
                    <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                </a>
            </div>

            <div class="wb-modal-body wb-stack wb-gap-4">
                <div class="wb-alert wb-alert-info">
                    <div>Contract details are read-only in this modal. `Admin -> System -> Block Types` remains a catalog screen, not a form builder or schema editor.</div>
                </div>

                @if ($contract && ! $contract->documented)
                    <div class="wb-alert wb-alert-info">
                        <div>{{ $contract->undocumentedMessage }}</div>
                    </div>
                @endif

                <div class="wb-card wb-card-muted">
                    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                        <strong>Catalog</strong>

                        <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                            <span class="wb-status-pill {{ $blockType->status === 'published' ? 'wb-status-active' : 'wb-status-pending' }}">{{ $blockType->status }}</span>
                            <span class="wb-status-pill {{ $contractStatusClass }}">{{ $contract?->currentContractStatus ?? 'not documented' }}</span>
                        </div>
                    </div>

                    <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                        <div class="wb-settings-row">
                            <div class="wb-settings-row-label"><strong>Slug</strong></div>
                            <div class="wb-settings-row-control"><span><code>{{ $contract?->slug ?? $blockType->slug }}</code></span></div>
                        </div>
                        <div class="wb-settings-row">
                            <div class="wb-settings-row-label"><strong>Category</strong></div>
                            <div class="wb-settings-row-control"><span>{{ $contract?->category ?: 'None documented' }}</span></div>
                        </div>
                        <div class="wb-settings-row">
                            <div class="wb-settings-row-label"><strong>Source type</strong></div>
                            <div class="wb-settings-row-control"><span><code>{{ $contract?->sourceType ?: ($blockType->source_type ?: 'static') }}</code></span></div>
                        </div>
                        <div class="wb-settings-row">
                            <div class="wb-settings-row-label"><strong>Support flags</strong></div>
                            <div class="wb-settings-row-control">
                                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                                    <span class="wb-status-pill wb-status-info">{{ ($contract?->isSystem ?? $blockType->is_system) ? 'System' : 'Install-specific' }}</span>
                                    <span class="wb-status-pill wb-status-info">{{ ($contract?->isContainer ?? $blockType->is_container) ? 'Container-capable' : 'Non-container' }}</span>
                                    <span class="wb-status-pill wb-status-info">{{ ($contract?->adminFormSource ?? null) ? 'Admin form documented' : 'Admin form undocumented' }}</span>
                                    <span class="wb-status-pill wb-status-info">{{ ($contract?->publicRendererSource ?? null) ? 'Renderer documented' : 'Renderer undocumented' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="wb-grid wb-grid-2">
                    <div class="wb-card wb-card-muted">
                        <div class="wb-card-header"><strong>Admin Form</strong></div>
                        <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                            <div class="wb-settings-row">
                                <div class="wb-settings-row-label"><strong>Admin form source</strong></div>
                                <div class="wb-settings-row-control">
                                    @if ($contract?->adminFormSource)
                                        <span><code>{{ $contract->adminFormSource }}</code></span>
                                    @else
                                        <span class="wb-text-sm wb-text-muted">None documented.</span>
                                    @endif
                                </div>
                            </div>
                            <div class="wb-settings-row">
                                <div class="wb-settings-row-label"><strong>Visible fields</strong></div>
                                <div class="wb-settings-row-control">
                                    @include('webblocks-cms::admin.block-types.partials.contract-items', [
                                        'items' => $contract?->adminFormFields ?? [],
                                        'empty' => 'None documented.',
                                        'code' => true,
                                    ])
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="wb-card wb-card-muted">
                        <div class="wb-card-header"><strong>Storage</strong></div>
                        <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                            <div class="wb-settings-row">
                                <div class="wb-settings-row-label"><strong>Storage ownership</strong></div>
                                <div class="wb-settings-row-control">
                                    @include('webblocks-cms::admin.block-types.partials.contract-items', [
                                        'items' => $contract?->storageFields ?? [],
                                        'empty' => 'None documented.',
                                    ])
                                </div>
                            </div>
                            <div class="wb-settings-row">
                                <div class="wb-settings-row-label"><strong>Shared/settings fields</strong></div>
                                <div class="wb-settings-row-control">
                                    @include('webblocks-cms::admin.block-types.partials.contract-items', [
                                        'items' => $contract?->sharedSettingsFields ?? [],
                                        'empty' => 'Not applicable.',
                                        'code' => true,
                                    ])
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="wb-grid wb-grid-2">
                    <div class="wb-card wb-card-muted">
                        <div class="wb-card-header"><strong>Translation</strong></div>
                        <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                            <div class="wb-settings-row">
                                <div class="wb-settings-row-label"><strong>Translation family</strong></div>
                                <div class="wb-settings-row-control"><span>{{ $contract?->translationFamily ? strtoupper($contract->translationFamily) : 'Not applicable' }}</span></div>
                            </div>
                            <div class="wb-settings-row">
                                <div class="wb-settings-row-label"><strong>Translatable fields</strong></div>
                                <div class="wb-settings-row-control">
                                    @include('webblocks-cms::admin.block-types.partials.contract-items', [
                                        'items' => $contract?->translatableFields ?? [],
                                        'empty' => 'Not applicable.',
                                        'code' => true,
                                    ])
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="wb-card wb-card-muted">
                        <div class="wb-card-header"><strong>Media / Relationships</strong></div>
                        <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                            @include('webblocks-cms::admin.block-types.partials.contract-items', [
                                'items' => $contract?->mediaRelationshipFields ?? [],
                                'empty' => 'Not applicable.',
                            ])
                        </div>
                    </div>
                </div>

                <div class="wb-grid wb-grid-2">
                    <div class="wb-card wb-card-muted">
                        <div class="wb-card-header"><strong>Children / Container Rules</strong></div>
                        <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                            @include('webblocks-cms::admin.block-types.partials.contract-items', [
                                'items' => $contract?->childContainerBehavior ?? [],
                                'empty' => 'Not applicable.',
                            ])

                            @if ($contract?->allowedChildTypeSlugs)
                                <div class="wb-settings-row">
                                    <div class="wb-settings-row-label"><strong>Helper child whitelist</strong></div>
                                    <div class="wb-settings-row-control">
                                        @include('webblocks-cms::admin.block-types.partials.contract-items', [
                                            'items' => $contract->allowedChildTypeSlugs,
                                            'empty' => 'Not applicable.',
                                            'code' => true,
                                        ])
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="wb-card wb-card-muted">
                        <div class="wb-card-header"><strong>Public Renderer</strong></div>
                        <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                            <div class="wb-settings-row">
                                <div class="wb-settings-row-label"><strong>Public renderer source</strong></div>
                                <div class="wb-settings-row-control">
                                    @if ($contract?->publicRendererSource)
                                        <span><code>{{ $contract->publicRendererSource }}</code></span>
                                    @else
                                        <span class="wb-text-sm wb-text-muted">None documented.</span>
                                    @endif
                                </div>
                            </div>
                            <div class="wb-settings-row">
                                <div class="wb-settings-row-label"><strong>Renderer root contract</strong></div>
                                <div class="wb-settings-row-control"><span>{{ $contract?->rendererRootContract ?? 'None documented.' }}</span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="wb-card wb-card-muted">
                    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                        <strong>Known Gaps</strong>
                        <span class="wb-status-pill {{ $contractStatusClass }}">{{ $contract?->currentContractStatus ?? 'not documented' }}</span>
                    </div>
                    <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                        @if ($contract && $contract->knownGaps !== [])
                            <div class="wb-alert wb-alert-warning">
                                <div>
                                    @include('webblocks-cms::admin.block-types.partials.contract-items', [
                                        'items' => $contract->knownGaps,
                                        'empty' => 'No documented gaps.',
                                    ])
                                </div>
                            </div>
                        @else
                            <div class="wb-alert wb-alert-info">
                                <div>No documented gaps.</div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                <div class="wb-flex wb-items-center wb-gap-3 wb-flex-wrap">
                    <a href="{{ $closeUrl }}" class="wb-btn wb-btn-secondary">Close</a>
                </div>
            </div>
        </div>
    </div>
</div>
