@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $adminLocale = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin($key, $adminLocale, $replace);
    $transferStatusLabel = static fn (?string $status) => $status ? $adminText('site_transfers.statuses.'.$status) : '-';
    $yesNoLabel = static fn (bool $value) => $value ? $adminText('common.yes') : $adminText('common.no');
    $requestedModal = trim((string) request()->query('modal', old('_site_export_modal', '')));
    $showExportModal = $requestedModal === 'create-export';
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $adminText('site_transfers.title'), 'heading' => $adminText('site_transfers.title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $adminText('site_transfers.title'),
        'description' => $adminText('site_transfers.description'),
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-stack wb-gap-4">
        <div class="wb-card" data-wb-admin-bulk-listing>
            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <strong>{{ $adminText('site_transfers.exports') }}</strong>
                    <span class="wb-status-pill wb-status-info">{{ $exports->total() }}</span>
                </div>

                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <a href="{{ route('admin.site-transfers.exports.index', ['modal' => 'create-export']) }}" class="wb-btn wb-btn-primary" aria-haspopup="dialog" aria-controls="siteTransferExportModal">{{ $adminText('site_transfers.run_export') }}</a>
                </div>
            </div>

            <div class="wb-card-body">
                @if ($exports->isEmpty())
                    <div class="wb-empty">
                        <div class="wb-empty-title">{{ $adminText('site_transfers.no_exports') }}</div>
                        <div class="wb-empty-text">{{ $adminText('site_transfers.no_exports_help') }}</div>
                    </div>
                @else
                    @include('webblocks-cms::admin.partials.listing-bulk-actions', [
                        'label' => $adminText('common.selected'),
                        'deleteTarget' => '#bulk-delete-site-exports-modal',
                        'deleteLabel' => $adminText('common.delete_selected'),
                    ])

                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-striped wb-table-hover">
                            <thead>
                                <tr>
                                    <th>
                                        <label class="wb-checkbox" for="select_all_visible_site_exports">
                                            <input id="select_all_visible_site_exports" type="checkbox" data-wb-admin-select-all-visible aria-label="{{ $adminText('site_transfers.select_all_exports') }}">
                                            <span class="wb-sr-only">{{ $adminText('site_transfers.select_all_exports') }}</span>
                                        </label>
                                    </th>
                                    <th>{{ $adminText('site_transfers.created_at') }}</th>
                                    <th>{{ $adminText('site_transfers.site') }}</th>
                                    <th>{{ $adminText('site_transfers.includes_media') }}</th>
                                    <th>{{ $adminText('site_transfers.package_size') }}</th>
                                    <th>{{ $adminText('common.status') }}</th>
                                    <th>{{ $adminText('common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($exports as $siteExport)
                                    <tr>
                                        <td>
                                            <label class="wb-checkbox" for="site_export_select_{{ $siteExport->id }}">
                                                <input id="site_export_select_{{ $siteExport->id }}" type="checkbox" value="{{ $siteExport->id }}" data-wb-admin-row-select aria-label="{{ $adminText('site_transfers.select_export', ['name' => $siteExport->archive_name ?? '#'.$siteExport->id]) }}">
                                                <span class="wb-sr-only">{{ $adminText('site_transfers.select_export', ['name' => $siteExport->archive_name ?? '#'.$siteExport->id]) }}</span>
                                            </label>
                                        </td>
                                        <td>{{ $siteExport->created_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                        <td>{{ $siteExport->site?->name ?? '-' }}</td>
                                        <td>{{ $yesNoLabel((bool) $siteExport->includes_media) }}</td>
                                        <td>{{ $siteExport->humanArchiveSize() }}</td>
                                        <td><span class="wb-status-pill {{ $siteExport->statusBadgeClass() }}">{{ $transferStatusLabel($siteExport->status) }}</span></td>
                                        <td class="wb-table-actions">
                                            <div class="wb-action-group">
                                                <a href="{{ route('admin.site-transfers.exports.show', $siteExport) }}" class="wb-action-btn wb-action-btn-view" title="{{ $adminText('site_transfers.export_details') }}" aria-label="{{ $adminText('site_transfers.export_details') }}">
                                                    <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                                                </a>

                                                @if ($siteExport->isCompleted() && $siteExport->archive_path)
                                                    <a href="{{ route('admin.site-transfers.exports.download', $siteExport) }}" class="wb-action-btn wb-action-btn-edit" title="{{ $adminText('site_transfers.download_export_package') }}" aria-label="{{ $adminText('site_transfers.download_export_package') }}">
                                                        <i class="wb-icon wb-icon-download" aria-hidden="true"></i>
                                                    </a>
                                                @endif

                                                <button
                                                    type="button"
                                                    class="wb-action-btn wb-action-btn-delete"
                                                    title="{{ $adminText('site_transfers.delete_export') }}"
                                                    aria-label="{{ $adminText('site_transfers.delete_export') }}"
                                                    data-wb-toggle="modal"
                                                    data-wb-target="#delete-site-export-{{ $siteExport->id }}-modal"
                                                >
                                                    <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    @push('overlays')
                                        @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
                                            'id' => 'delete-site-export-'.$siteExport->id.'-modal',
                                            'title' => $adminText('site_transfers.delete_export_title'),
                                            'description' => $adminText('site_transfers.delete_export_description'),
                                            'action' => route('admin.site-transfers.exports.destroy', $siteExport),
                                            'method' => 'DELETE',
                                            'submitLabel' => $adminText('site_transfers.delete_export'),
                                        ])
                                            <div class="wb-card wb-card-muted">
                                                <div class="wb-card-body wb-stack wb-gap-2">
                                                    <div><strong>{{ $siteExport->archive_name ?? $adminText('site_transfers.export_number', ['id' => $siteExport->id]) }}</strong></div>
                                                    <div class="wb-text-sm wb-text-muted">{{ $adminText('common.status') }} {{ $transferStatusLabel($siteExport->status) }} | {{ $adminText('site_transfers.site') }} {{ $siteExport->site?->name ?? '-' }}</div>
                                                </div>
                                            </div>

                                            <p class="wb-text-sm wb-text-muted">{{ $adminText('site_transfers.delete_export_warning') }}</p>
                                        @endcomponent
                                    @endpush
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            @include('webblocks-cms::admin.partials.pagination', ['paginator' => $exports, 'compact' => true, 'ariaLabel' => $adminText('site_transfers.exports_pagination')])
        </div>

        @if ($exports->isNotEmpty())
            @push('overlays')
                @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
                    'id' => 'bulk-delete-site-exports-modal',
                    'title' => $adminText('site_transfers.delete_selected_exports_title'),
                    'description' => $adminText('site_transfers.delete_selected_exports_description'),
                    'action' => route('admin.site-transfers.exports.bulk-destroy'),
                    'method' => 'DELETE',
                    'submitLabel' => $adminText('common.delete_selected'),
                    'formAttributes' => [
                        'data-wb-admin-bulk-delete-form' => true,
                        'data-wb-admin-bulk-input-name' => 'site_export_ids[]',
                    ],
                    'submitAttributes' => [
                        'data-wb-admin-bulk-delete-submit' => true,
                        'disabled' => true,
                    ],
                ])
                    <div class="wb-card wb-card-muted">
                        <div class="wb-card-body wb-stack wb-gap-2">
                            <strong><span data-wb-admin-bulk-modal-count>0</span> {{ $adminText('site_transfers.selected_exports_will_be_deleted') }}</strong>
                            <p class="wb-text-sm wb-text-muted">{{ $adminText('site_transfers.bulk_delete_exports_help') }}</p>
                        </div>
                    </div>

                    <div data-wb-admin-bulk-inputs></div>
                    <input type="hidden" name="site_export_ids[]" value="" disabled data-wb-admin-bulk-empty-input>
                @endcomponent
            @endpush
        @endif

        <div class="wb-card" data-wb-admin-bulk-listing>
            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <strong>{{ $adminText('site_transfers.imports') }}</strong>
                    <span class="wb-status-pill wb-status-info">{{ $imports->total() }}</span>
                </div>

                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <a href="{{ route('admin.site-transfers.imports.create') }}" class="wb-btn wb-btn-primary">{{ $adminText('site_transfers.run_import') }}</a>
                </div>
            </div>

            <div class="wb-card-body">
                @if ($imports->isEmpty())
                    <div class="wb-empty">
                        <div class="wb-empty-title">{{ $adminText('site_transfers.no_imports') }}</div>
                        <div class="wb-empty-text">{{ $adminText('site_transfers.no_imports_help') }}</div>
                    </div>
                @else
                    @include('webblocks-cms::admin.partials.listing-bulk-actions', [
                        'label' => $adminText('common.selected'),
                        'deleteTarget' => '#bulk-delete-site-imports-modal',
                        'deleteLabel' => $adminText('common.delete_selected'),
                    ])

                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-striped wb-table-hover">
                            <thead>
                                <tr>
                                    <th>
                                        <label class="wb-checkbox" for="select_all_visible_site_imports">
                                            <input id="select_all_visible_site_imports" type="checkbox" data-wb-admin-select-all-visible aria-label="{{ $adminText('site_transfers.select_all_imports') }}">
                                            <span class="wb-sr-only">{{ $adminText('site_transfers.select_all_imports') }}</span>
                                        </label>
                                    </th>
                                    <th>{{ $adminText('site_transfers.created_at') }}</th>
                                    <th>{{ $adminText('site_transfers.imported_site_result') }}</th>
                                    <th>{{ $adminText('site_transfers.source_package_name') }}</th>
                                    <th>{{ $adminText('common.status') }}</th>
                                    <th>{{ $adminText('common.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($imports as $siteImport)
                                    <tr>
                                        <td>
                                            <label class="wb-checkbox" for="site_import_select_{{ $siteImport->id }}">
                                                <input id="site_import_select_{{ $siteImport->id }}" type="checkbox" value="{{ $siteImport->id }}" data-wb-admin-row-select aria-label="{{ $adminText('site_transfers.select_import', ['name' => $siteImport->source_archive_name ?? '#'.$siteImport->id]) }}">
                                                <span class="wb-sr-only">{{ $adminText('site_transfers.select_import', ['name' => $siteImport->source_archive_name ?? '#'.$siteImport->id]) }}</span>
                                            </label>
                                        </td>
                                        <td>{{ $siteImport->created_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                        <td>
                                            @if ($siteImport->targetSite)
                                                {{ $siteImport->targetSite->name }} ({{ $siteImport->targetSite->handle }})
                                            @else
                                                {{ $siteImport->imported_site_handle ?? $adminText('site_transfers.pending_failed') }}
                                            @endif
                                        </td>
                                        <td>{{ $siteImport->source_archive_name ?? '-' }}</td>
                                        <td><span class="wb-status-pill {{ $siteImport->statusBadgeClass() }}">{{ $transferStatusLabel($siteImport->status) }}</span></td>
                                        <td class="wb-table-actions">
                                            <div class="wb-action-group">
                                                <a href="{{ route('admin.site-transfers.imports.show', $siteImport) }}" class="wb-action-btn wb-action-btn-view" title="{{ $adminText('site_transfers.import_details') }}" aria-label="{{ $adminText('site_transfers.import_details') }}">
                                                    <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                                                </a>

                                                <button
                                                    type="button"
                                                    class="wb-action-btn wb-action-btn-delete"
                                                    title="{{ $adminText('site_transfers.delete_import_log') }}"
                                                    aria-label="{{ $adminText('site_transfers.delete_import_log') }}"
                                                    data-wb-toggle="modal"
                                                    data-wb-target="#delete-site-import-{{ $siteImport->id }}-modal"
                                                >
                                                    <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>

                                    @push('overlays')
                                        @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
                                            'id' => 'delete-site-import-'.$siteImport->id.'-modal',
                                            'title' => $adminText('site_transfers.delete_import_title'),
                                            'description' => $adminText('site_transfers.delete_import_description'),
                                            'action' => route('admin.site-transfers.imports.destroy', $siteImport),
                                            'method' => 'DELETE',
                                            'submitLabel' => $adminText('site_transfers.delete_import_log'),
                                        ])
                                            <div class="wb-card wb-card-muted">
                                                <div class="wb-card-body wb-stack wb-gap-2">
                                                    <div><strong>{{ $siteImport->source_archive_name ?? $adminText('site_transfers.import_number', ['id' => $siteImport->id]) }}</strong></div>
                                                    <div class="wb-text-sm wb-text-muted">{{ $adminText('common.status') }} {{ $transferStatusLabel($siteImport->status) }} | {{ $adminText('site_transfers.result') }} {{ $siteImport->targetSite?->name ?? ($siteImport->imported_site_handle ?? $adminText('site_transfers.pending_failed')) }}</div>
                                                </div>
                                            </div>

                                            <p class="wb-text-sm wb-text-muted">{{ $adminText('site_transfers.import_content_remains') }}</p>
                                        @endcomponent
                                    @endpush
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            @include('webblocks-cms::admin.partials.pagination', ['paginator' => $imports, 'compact' => true, 'ariaLabel' => $adminText('site_transfers.imports_pagination')])
        </div>

        @if ($imports->isNotEmpty())
            @push('overlays')
                @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
                    'id' => 'bulk-delete-site-imports-modal',
                    'title' => $adminText('site_transfers.delete_selected_imports_title'),
                    'description' => $adminText('site_transfers.delete_selected_imports_description'),
                    'action' => route('admin.site-transfers.imports.bulk-destroy'),
                    'method' => 'DELETE',
                    'submitLabel' => $adminText('common.delete_selected'),
                    'formAttributes' => [
                        'data-wb-admin-bulk-delete-form' => true,
                        'data-wb-admin-bulk-input-name' => 'site_import_ids[]',
                    ],
                    'submitAttributes' => [
                        'data-wb-admin-bulk-delete-submit' => true,
                        'disabled' => true,
                    ],
                ])
                    <div class="wb-card wb-card-muted">
                        <div class="wb-card-body wb-stack wb-gap-2">
                            <strong><span data-wb-admin-bulk-modal-count>0</span> {{ $adminText('site_transfers.selected_imports_will_be_deleted') }}</strong>
                            <p class="wb-text-sm wb-text-muted">{{ $adminText('site_transfers.bulk_delete_imports_help') }}</p>
                        </div>
                    </div>

                    <div data-wb-admin-bulk-inputs></div>
                    <input type="hidden" name="site_import_ids[]" value="" disabled data-wb-admin-bulk-empty-input>
                @endcomponent
            @endpush
        @endif
    </div>

    @include('webblocks-cms::admin.site-transfers.partials.export-modal', [
        'modalId' => 'siteTransferExportModal',
        'modalTitle' => $adminText('site_transfers.export_site'),
        'modalDescription' => $adminText('site_transfers.export_site_description'),
        'sites' => $sites,
        'exportablePages' => $exportablePages ?? [],
        'show' => $showExportModal,
        'closeUrl' => route('admin.site-transfers.exports.index'),
        'formAction' => route('admin.site-transfers.exports.store'),
        'modalKey' => 'create-export',
    ])

    @push('scripts')
        @php($bulkActionsJsPath = public_path('cms/js/admin/listing-bulk-actions.js'))
        @if (is_file($bulkActionsJsPath))
            <script src="{{ asset('cms/js/admin/listing-bulk-actions.js') }}?v={{ filemtime($bulkActionsJsPath) }}" defer></script>
        @endif
    @endpush
@endsection
