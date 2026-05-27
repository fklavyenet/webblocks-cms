@extends('webblocks-cms::layouts.admin', ['title' => 'WebBlocks UI Releases', 'heading' => 'WebBlocks UI Releases'])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'WebBlocks UI Releases',
        'description' => 'Track first-party WebBlocks UI release metadata, checksums, and local CDN preparation state.',
        'actions' => auth()->user()?->can('webblocks-ui-manager.manage')
            ? '<a href="'.route('webblocks.plugins.webblocks_ui_manager.releases.create').'" class="wb-btn wb-btn-primary">New Release</a>'
            : null,
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-header">
            <strong>Tracked Releases</strong>
        </div>
        <div class="wb-card-body">
            @if ($releases->isEmpty())
                <div class="wb-empty">
                    <div class="wb-empty-title">No WebBlocks UI releases recorded yet.</div>
                    <div class="wb-empty-text">Create release metadata or run the preparation command with local artifact files.</div>
                </div>
            @else
                <div class="wb-table-wrap">
                    <table class="wb-table">
                        <thead>
                            <tr>
                                <th>Version</th>
                                <th>Status</th>
                                <th>Artifacts</th>
                                <th>CDN Target</th>
                                <th>Prepared</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($releases as $release)
                                <tr>
                                    <td><a href="{{ route('webblocks.plugins.webblocks_ui_manager.releases.show', $release) }}"><strong>{{ $release->label ?: $release->version }}</strong></a></td>
                                    <td><span class="wb-status {{ $release->statusBadgeClass() }}">{{ ucfirst($release->statusLabel()) }}</span></td>
                                    <td>{{ $release->artifacts_count }}</td>
                                    <td><code>{{ $release->cdn_base_path ?: '-' }}</code></td>
                                    <td>{{ $release->prepared_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td><a href="{{ route('webblocks.plugins.webblocks_ui_manager.releases.show', $release) }}" class="wb-btn wb-btn-secondary wb-btn-sm">Open</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    @include('webblocks-cms::admin.partials.pagination', ['paginator' => $releases])
@endsection
