@extends('webblocks-cms::layouts.admin', ['title' => $release->label ?: $release->version, 'heading' => 'WebBlocks UI Release'])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $release->label ?: $release->version,
        'description' => 'Review release metadata, artifact checksums, publish readiness, and local CDN status.',
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
                <div><strong>Published:</strong> {{ $release->published_at?->format('Y-m-d H:i:s') ?? '-' }}</div>
                @if ($release->notes)
                    <div class="wb-text-sm wb-text-muted">{{ $release->notes }}</div>
                @endif
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header"><strong>Publish Workflow</strong></div>
            <div class="wb-card-body wb-stack wb-gap-2">
                <div><strong>Readiness:</strong> <span class="wb-status {{ $release->statusBadgeClass() }}">{{ ucfirst($release->statusLabel()) }}</span></div>
                <div class="wb-text-sm wb-text-muted">Dry-run validates source files, target paths, checksums, manifest consistency, idempotency, and CDN root safety without writing files.</div>
                @can('webblocks-ui-manager.publish')
                    <div class="wb-cluster wb-gap-2">
                        <form method="POST" action="{{ route('webblocks.plugins.webblocks_ui_manager.releases.publish.dry-run', $release) }}">
                            @csrf
                            <button type="submit" class="wb-btn wb-btn-secondary">Dry Run Publish</button>
                        </form>
                        <a href="{{ route('webblocks.plugins.webblocks_ui_manager.releases.show', ['release' => $release, 'modal' => 'publish']) }}" class="wb-btn wb-btn-primary" aria-haspopup="dialog">Publish</a>
                    </div>
                @endcan
                <div class="wb-text-sm wb-text-muted">Command:</div>
                <code style="white-space: normal; word-break: break-word; display: block;">php artisan webblocks-ui-manager:publish-release {{ $release->version }} --dry-run</code>
                <code style="white-space: normal; word-break: break-word; display: block;">php artisan webblocks-ui-manager:publish-release {{ $release->version }}</code>
            </div>
        </div>
    </div>

    <div class="wb-card">
        <div class="wb-card-header"><strong>Latest Publish Result</strong></div>
        <div class="wb-card-body">
            @if ($latestPublishRun)
                <div class="wb-grid wb-grid-2 wb-gap-3">
                    <div><strong>Mode:</strong> {{ $latestPublishRun->mode }}</div>
                    <div><strong>Status:</strong> <span class="wb-status {{ $latestPublishRun->statusBadgeClass() }}">{{ ucfirst($latestPublishRun->status) }}</span></div>
                    <div><strong>Target:</strong> <code>{{ $latestPublishRun->target_release_path }}</code></div>
                    <div><strong>Finished:</strong> {{ $latestPublishRun->finished_at?->format('Y-m-d H:i:s') ?? '-' }}</div>
                </div>
                <p class="wb-text-sm wb-text-muted">{{ $latestPublishRun->message }}</p>
                @if (! empty($latestPublishRun->operations))
                    <div class="wb-table-wrap">
                        <table class="wb-table">
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>Artifact</th>
                                    <th>Target</th>
                                    <th>Checksum</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($latestPublishRun->operations as $operation)
                                    <tr>
                                        <td>{{ $operation['action'] }}</td>
                                        <td><code>{{ $operation['artifact'] }}</code></td>
                                        <td><code>{{ $operation['target_path'] }}</code></td>
                                        <td><code>{{ $operation['checksum_sha256'] }}</code></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @else
                <div class="wb-empty wb-empty-sm">
                    <div class="wb-empty-title">No publish runs yet.</div>
                    <div class="wb-empty-text">Run a dry-run first to validate the local CDN publish plan.</div>
                </div>
            @endif
        </div>
    </div>

    <div class="wb-card wb-card-muted">
        <div class="wb-card-header"><strong>Safe Preparation Command</strong></div>
        <div class="wb-card-body wb-stack wb-gap-2">
            <div class="wb-text-sm wb-text-muted">This records local artifact checksums and manifest metadata before publish.</div>
                <code style="white-space: normal; word-break: break-word; display: block;">php artisan webblocks-ui-manager:prepare-release {{ $release->version }} --artifact=/path/to/webblocks-ui.css --artifact=/path/to/webblocks-ui.js</code>
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

    @if ($showPublishModal)
        <div class="wb-overlay-layer wb-overlay-layer--dialog">
            <div class="wb-modal wb-modal-lg is-open" id="webblocks-ui-publish-modal" role="dialog" aria-modal="true" aria-labelledby="webblocks-ui-publish-title">
                <div class="wb-modal-dialog">
                    <div class="wb-modal-header">
                        <div>
                            <h2 class="wb-modal-title" id="webblocks-ui-publish-title">Publish WebBlocks UI Release</h2>
                            <span class="wb-text-sm wb-text-muted">This writes only to the configured local CDN target after validation passes.</span>
                        </div>
                        <a href="{{ route('webblocks.plugins.webblocks_ui_manager.releases.show', $release) }}" class="wb-modal-close" aria-label="Close publish confirmation modal"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></a>
                    </div>
                    <form method="POST" action="{{ route('webblocks.plugins.webblocks_ui_manager.releases.publish', $release) }}">
                        @csrf
                        <div class="wb-modal-body wb-stack wb-gap-3">
                            <p><strong>{{ $release->version }}</strong> will be published to <code>{{ $release->cdn_base_path }}</code>.</p>
                            <p class="wb-text-sm wb-text-muted">Existing files with matching checksums are skipped. Existing files with different checksums block the publish.</p>
                        </div>
                        <div class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                            <a href="{{ route('webblocks.plugins.webblocks_ui_manager.releases.show', $release) }}" class="wb-btn wb-btn-secondary">Cancel</a>
                            <button type="submit" class="wb-btn wb-btn-primary">Publish Release</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endsection
