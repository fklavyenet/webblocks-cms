@extends('layouts.admin', ['title' => 'Imports', 'heading' => 'Imports'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Site Imports',
        'description' => 'Review validated and completed site import packages. Imported site content stays intact if you delete an import log.',
        'count' => $imports->total(),
        'actions' => '<div class="wb-cluster wb-cluster-2"><a href="'.route('admin.site-transfers.exports.index').'" class="wb-btn wb-btn-secondary">Exports</a><a href="'.route('admin.site-transfers.imports.create').'" class="wb-btn wb-btn-primary">Run Import</a></div>',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
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

        @include('admin.partials.pagination', ['paginator' => $imports])
    </div>
@endsection
