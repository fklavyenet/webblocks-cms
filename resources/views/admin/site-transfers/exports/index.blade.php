@extends('layouts.admin', ['title' => 'Export / Import', 'heading' => 'Export / Import'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Site Exports',
        'description' => 'Create portable site export packages for migration, duplication, and transfer between installs. This stays separate from environment backups.',
        'count' => $exports->total(),
        'actions' => '<div class="wb-cluster wb-cluster-2"><a href="'.route('admin.site-transfers.imports.index').'" class="wb-btn wb-btn-secondary">Imports</a><a href="'.route('admin.site-transfers.imports.create').'" class="wb-btn wb-btn-secondary">Run Import</a></div>',
    ])

    @include('admin.partials.flash')

    @if ($errors->has('site_export'))
        <div class="wb-alert wb-alert-danger">{{ $errors->first('site_export') }}</div>
    @endif

    <div class="wb-stack wb-stack-4">
        <div class="wb-card wb-card-muted">
            <div class="wb-card-header"><strong>Create Export</strong></div>

            <div class="wb-card-body">
                <form method="POST" action="{{ route('admin.site-transfers.exports.store') }}" class="wb-stack wb-gap-4">
                    @csrf

                    <div class="wb-grid wb-grid-2">
                        <div class="wb-stack wb-gap-2">
                            <label for="site_id">Site</label>
                            <select id="site_id" name="site_id" class="wb-select" required>
                                @foreach ($sites as $site)
                                    <option value="{{ $site->id }}" @selected((int) old('site_id') === $site->id)>{{ $site->name }} ({{ $site->handle }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="wb-stack wb-gap-2">
                            <label class="wb-checkbox" for="includes_media">
                                <input id="includes_media" type="checkbox" name="includes_media" value="1" @checked(old('includes_media', true))>
                                <span>Include media/assets</span>
                            </label>

                            <div class="wb-text-sm wb-text-muted">When enabled, the export package includes referenced asset records and the actual files from CMS-managed storage.</div>
                        </div>
                    </div>

                    <div class="wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                        <div class="wb-flex wb-items-center wb-gap-2 wb-flex-wrap">
                            <a href="{{ route('admin.site-transfers.exports.index') }}" class="wb-btn wb-btn-secondary">Cancel</a>
                            <button type="submit" class="wb-btn wb-btn-primary">Run Export</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="wb-card">
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

            @include('admin.partials.pagination', ['paginator' => $exports])
        </div>
    </div>
@endsection
