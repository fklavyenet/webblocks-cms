@extends('webblocks-cms::layouts.admin', ['title' => 'Export / Import', 'heading' => 'Export / Import'])

@php
    $requestedModal = trim((string) request()->query('modal', old('_site_export_modal', '')));
    $showExportModal = $requestedModal === 'create-export';
@endphp

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'Export / Import',
        'description' => 'Run portable site exports, inspect package history, and validate or import transfer packages from one operational screen.',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-stack wb-gap-4">
        <div class="wb-card">
            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <strong>Site Exports</strong>
                    <span class="wb-status-pill wb-status-info">{{ $exports->total() }}</span>
                </div>

                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <a href="{{ route('admin.site-transfers.exports.index', ['modal' => 'create-export']) }}" class="wb-btn wb-btn-primary" aria-haspopup="dialog" aria-controls="siteTransferExportModal">Run Export</a>
                </div>
            </div>

            <div class="wb-card-body">
                @if ($exports->isEmpty())
                    <div class="wb-empty">
                        <div class="wb-empty-title">No site exports yet</div>
                        <div class="wb-empty-text">The first completed site export package will appear here with download and detail actions.</div>
                    </div>
                @else
                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-striped wb-table-hover">
                            <thead>
                                <tr>
                                    <th>Created at</th>
                                    <th>Site</th>
                                    <th>Includes media</th>
                                    <th>Package size</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($exports as $siteExport)
                                    <tr>
                                        <td>{{ $siteExport->created_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                        <td>{{ $siteExport->site?->name ?? '-' }}</td>
                                        <td>{{ $siteExport->includes_media ? 'Yes' : 'No' }}</td>
                                        <td>{{ $siteExport->humanArchiveSize() }}</td>
                                        <td><span class="wb-status-pill {{ $siteExport->statusBadgeClass() }}">{{ $siteExport->statusLabel() }}</span></td>
                                        <td>
                                            <div class="wb-cluster wb-cluster-2 wb-row-end">
                                                <a href="{{ route('admin.site-transfers.exports.show', $siteExport) }}" class="wb-action-btn wb-action-btn-view" title="Export details" aria-label="Export details">
                                                    <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                                                </a>

                                                @if ($siteExport->isCompleted() && $siteExport->archive_path)
                                                    <a href="{{ route('admin.site-transfers.exports.download', $siteExport) }}" class="wb-action-btn wb-action-btn-edit" title="Download export package" aria-label="Download export package">
                                                        <i class="wb-icon wb-icon-download" aria-hidden="true"></i>
                                                    </a>
                                                @endif

                                                <form method="POST" action="{{ route('admin.site-transfers.exports.destroy', $siteExport) }}" onsubmit="return confirm('Delete this site export record and archive file?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="wb-action-btn wb-action-btn-delete" title="Delete export" aria-label="Delete export">
                                                        <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            @include('webblocks-cms::admin.partials.pagination', ['paginator' => $exports, 'compact' => true, 'ariaLabel' => 'Exports pagination'])
        </div>

        <div class="wb-card">
            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <strong>Site Imports</strong>
                    <span class="wb-status-pill wb-status-info">{{ $imports->total() }}</span>
                </div>

                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <a href="{{ route('admin.site-transfers.imports.create') }}" class="wb-btn wb-btn-primary">Run Import</a>
                </div>
            </div>

            <div class="wb-card-body">
                @if ($imports->isEmpty())
                    <div class="wb-empty">
                        <div class="wb-empty-title">No site imports yet</div>
                        <div class="wb-empty-text">Validated and completed site imports will appear here with result and log details.</div>
                    </div>
                @else
                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-striped wb-table-hover">
                            <thead>
                                <tr>
                                    <th>Created at</th>
                                    <th>Imported site/result</th>
                                    <th>Source package name</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($imports as $siteImport)
                                    <tr>
                                        <td>{{ $siteImport->created_at?->format('Y-m-d H:i:s') ?? '-' }}</td>
                                        <td>
                                            @if ($siteImport->targetSite)
                                                {{ $siteImport->targetSite->name }} ({{ $siteImport->targetSite->handle }})
                                            @else
                                                {{ $siteImport->imported_site_handle ?? 'Pending / failed' }}
                                            @endif
                                        </td>
                                        <td>{{ $siteImport->source_archive_name ?? '-' }}</td>
                                        <td><span class="wb-status-pill {{ $siteImport->statusBadgeClass() }}">{{ $siteImport->statusLabel() }}</span></td>
                                        <td>
                                            <div class="wb-cluster wb-cluster-2 wb-row-end">
                                                <a href="{{ route('admin.site-transfers.imports.show', $siteImport) }}" class="wb-action-btn wb-action-btn-view" title="Import details" aria-label="Import details">
                                                    <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                                                </a>

                                                <form method="POST" action="{{ route('admin.site-transfers.imports.destroy', $siteImport) }}" onsubmit="return confirm('Delete this import log and stored package archive? Imported site content will remain.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="wb-action-btn wb-action-btn-delete" title="Delete import log" aria-label="Delete import log">
                                                        <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            @include('webblocks-cms::admin.partials.pagination', ['paginator' => $imports, 'compact' => true, 'ariaLabel' => 'Imports pagination'])
        </div>
    </div>

    @include('webblocks-cms::admin.site-transfers.partials.export-modal', [
        'modalId' => 'siteTransferExportModal',
        'modalTitle' => 'Export Site',
        'modalDescription' => 'Create a portable site export package for migration, duplication, or transfer between installs.',
        'sites' => $sites,
        'show' => $showExportModal,
        'closeUrl' => route('admin.site-transfers.exports.index'),
        'formAction' => route('admin.site-transfers.exports.store'),
        'modalKey' => 'create-export',
    ])
@endsection
