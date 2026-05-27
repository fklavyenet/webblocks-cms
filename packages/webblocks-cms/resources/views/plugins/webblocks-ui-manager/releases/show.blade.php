@extends('webblocks-cms::layouts.admin', ['title' => $release->label ?: $release->version, 'heading' => 'WebBlocks UI Release'])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $release->label ?: $release->version,
        'description' => 'Review release metadata, artifact checksums, and manifest readiness.',
        'actions' => auth()->user()?->can('webblocks-ui-manager.manage')
            ? '<a href="'.route('webblocks.plugins.webblocks_ui_manager.releases.edit', $release).'" class="wb-btn wb-btn-secondary">Edit Metadata</a>'
            : null,
    ])

    @include('webblocks-cms::admin.partials.flash')

    <p><a href="{{ route('webblocks.plugins.webblocks_ui_manager.releases.index') }}">Back to Releases</a></p>

    <div class="wb-grid wb-grid-2">
        <div class="wb-card">
            <div class="wb-card-header"><strong>Release Metadata</strong></div>
            <div class="wb-card-body wb-stack wb-gap-3">
                <div><strong>Version:</strong> <code>{{ $release->version }}</code></div>
                <div><strong>Status:</strong> <span class="wb-status {{ $release->statusBadgeClass() }}">{{ ucfirst($release->statusLabel()) }}</span></div>
                <div><strong>CDN Path:</strong> <code>{{ $release->cdn_base_path ?: '-' }}</code></div>
                <div><strong>CDN URL:</strong> <code>{{ $release->cdn_base_url ?: '-' }}</code></div>
                <div><strong>Manifest:</strong> <code>{{ $release->manifest_path ?: '-' }}</code></div>
                <div><strong>Prepared:</strong> {{ $release->prepared_at?->format('Y-m-d H:i:s') ?? '-' }}</div>
                @if ($release->notes)
                    <div class="wb-text-sm wb-text-muted">{{ $release->notes }}</div>
                @endif
            </div>
        </div>

        <div class="wb-card wb-card-muted">
            <div class="wb-card-header"><strong>Safe Preparation Command</strong></div>
            <div class="wb-card-body wb-stack wb-gap-2">
                <div class="wb-text-sm wb-text-muted">This foundation records local artifact checksums and manifest metadata. It does not publish to production CDN.</div>
                <code style="white-space: normal; word-break: break-word; display: block;">ddev artisan webblocks-ui-manager:prepare-release {{ $release->version }} --artifact=/path/to/webblocks-ui.css --artifact=/path/to/webblocks-ui.js</code>
            </div>
        </div>
    </div>

    <div class="wb-card">
        <div class="wb-card-header"><strong>Artifacts</strong></div>
        <div class="wb-card-body">
            @if ($release->artifacts->isEmpty())
                <div class="wb-empty wb-empty-sm">
                    <div class="wb-empty-title">No artifacts prepared yet.</div>
                </div>
            @else
                <div class="wb-table-wrap">
                    <table class="wb-table">
                        <thead>
                            <tr>
                                <th>Handle</th>
                                <th>Target</th>
                                <th>Checksum</th>
                                <th>Size</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($release->artifacts as $artifact)
                                <tr>
                                    <td><code>{{ $artifact->handle }}</code></td>
                                    <td><code>{{ $artifact->target_path }}</code></td>
                                    <td><code>{{ $artifact->checksum_sha256 }}</code></td>
                                    <td>{{ $artifact->humanSize() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@endsection
