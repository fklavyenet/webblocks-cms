@php
    $drawerId = $drawerId ?? 'pageDetailsModal';
    $drawerTitleId = $drawerId.'Title';
    $defaultPublicUrl = $page->publicUrl();
    $defaultPublicPath = $page->publicPath();
    $publishedAt = data_get($page, 'published_at');
    $reviewRequestedAt = data_get($page, 'review_requested_at');
    $slotCount = $page->slots_count ?? ($page->relationLoaded('slots') ? $page->slots->count() : $page->slots()->count());
    $blockCount = $page->blocks_count ?? ($page->relationLoaded('blocks') ? $page->blocks->count() : $page->blocks()->count());
    $closeUrl = $closeUrl ?? route('admin.pages.edit', $page);
    $pageReturnUrl = $pageReturnUrl ?? route('admin.pages.index');
    $publishedLabel = $publishedAt instanceof \Illuminate\Support\Carbon
        ? $publishedAt->format('Y-m-d H:i')
        : ($publishedAt ?: 'Not recorded');
    $reviewRequestedLabel = $reviewRequestedAt instanceof \Illuminate\Support\Carbon
        ? $reviewRequestedAt->format('Y-m-d H:i')
        : ($reviewRequestedAt ?: 'Not recorded');
    $localeSummaries = collect($page->translationStatusForSite())
        ->map(fn (array $translationStatus) => strtoupper($translationStatus['locale']->code).': '.($translationStatus['translation']?->slug ?? 'Missing').($translationStatus['public_path'] ? ' | '.$translationStatus['public_path'] : ''));
@endphp

<div class="wb-overlay-layer wb-overlay-layer--dialog">
    <div class="wb-overlay-backdrop"></div>

    <div class="wb-modal wb-modal-xl is-open" id="{{ $drawerId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $drawerTitleId }}">
        <div class="wb-modal-dialog">
            <div class="wb-modal-header">
                <div class="wb-stack wb-gap-1">
                    <h2 class="wb-modal-title" id="{{ $drawerTitleId }}">Page Details</h2>
                    <span class="wb-text-sm wb-text-muted">Review page metadata without leaving the index.</span>
                </div>
                <a href="{{ $closeUrl }}" class="wb-modal-close" aria-label="Close page details">
                    <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                </a>
            </div>

            <div class="wb-modal-body">
                <div class="wb-stack wb-gap-4">
                    <div class="wb-grid wb-grid-2">
                        <div class="wb-card">
                            <div class="wb-card-header"><strong>Page</strong></div>
                            <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                                <div class="wb-settings-row">
                                    <div class="wb-settings-row-label"><strong>ID</strong></div>
                                    <div class="wb-settings-row-control"><span>{{ $page->id }}</span></div>
                                </div>
                                <div class="wb-settings-row">
                                    <div class="wb-settings-row-label"><strong>Name</strong></div>
                                    <div class="wb-settings-row-control"><span>{{ $page->title }}</span></div>
                                </div>
                                <div class="wb-settings-row">
                                    <div class="wb-settings-row-label"><strong>Slug</strong></div>
                                    <div class="wb-settings-row-control"><span><code>{{ $page->slug }}</code></span></div>
                                </div>
                                <div class="wb-settings-row">
                                    <div class="wb-settings-row-label"><strong>Path</strong></div>
                                    <div class="wb-settings-row-control"><span><code>{{ $defaultPublicPath ?? 'Missing' }}</code></span></div>
                                </div>
                                <div class="wb-settings-row">
                                    <div class="wb-settings-row-label"><strong>Site</strong></div>
                                    <div class="wb-settings-row-control"><span>{{ $page->site?->name }}</span></div>
                                </div>
                                <div class="wb-settings-row">
                                    <div class="wb-settings-row-label"><strong>Default URL</strong></div>
                                    <div class="wb-settings-row-control"><span><code>{{ $defaultPublicUrl ?? 'Missing' }}</code></span></div>
                                </div>
                                <div class="wb-settings-row">
                                    <div class="wb-settings-row-label"><strong>Locales</strong></div>
                                    <div class="wb-settings-row-control">
                                        <div class="wb-stack wb-gap-1">
                                            @foreach ($localeSummaries as $localeSummary)
                                                <span>{{ $localeSummary }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                                <div class="wb-settings-row">
                                    <div class="wb-settings-row-label"><strong>Slot count</strong></div>
                                    <div class="wb-settings-row-control"><span>{{ $slotCount }}</span></div>
                                </div>
                                <div class="wb-settings-row">
                                    <div class="wb-settings-row-label"><strong>Block count</strong></div>
                                    <div class="wb-settings-row-control"><span>{{ $blockCount }}</span></div>
                                </div>
                            </div>
                        </div>

                        <div class="wb-card">
                            <div class="wb-card-header"><strong>Status &amp; Audit</strong></div>
                            <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                                <div class="wb-settings-row">
                                    <div class="wb-settings-row-label"><strong>Status</strong></div>
                                    <div class="wb-settings-row-control"><span class="wb-status-pill {{ $page->workflowBadgeClass() }}">{{ $page->workflowLabel() }}</span></div>
                                </div>
                                <div class="wb-settings-row">
                                    <div class="wb-settings-row-label"><strong>Review Requested</strong></div>
                                    <div class="wb-settings-row-control"><span>{{ $reviewRequestedLabel }}</span></div>
                                </div>
                                <div class="wb-settings-row">
                                    <div class="wb-settings-row-label"><strong>Published</strong></div>
                                    <div class="wb-settings-row-control"><span>{{ $publishedLabel }}</span></div>
                                </div>
                                <div class="wb-settings-row">
                                    <div class="wb-settings-row-label"><strong>Created by</strong></div>
                                    <div class="wb-settings-row-control"><span>@include('admin.partials.audit-actor', ['actor' => $page->createdByUser])</span></div>
                                </div>
                                <div class="wb-settings-row">
                                    <div class="wb-settings-row-label"><strong>Last edited by</strong></div>
                                    <div class="wb-settings-row-control"><span>@include('admin.partials.audit-actor', ['actor' => $page->updatedByUser])</span></div>
                                </div>
                                <div class="wb-settings-row">
                                    <div class="wb-settings-row-label"><strong>Published by</strong></div>
                                    <div class="wb-settings-row-control"><span>@include('admin.partials.audit-actor', ['actor' => $page->publishedByUser])</span></div>
                                </div>
                                <div class="wb-settings-row">
                                    <div class="wb-settings-row-label"><strong>Archived by</strong></div>
                                    <div class="wb-settings-row-control"><span>@include('admin.partials.audit-actor', ['actor' => $page->archivedByUser])</span></div>
                                </div>
                                <div class="wb-settings-row">
                                    <div class="wb-settings-row-label"><strong>Review requested by</strong></div>
                                    <div class="wb-settings-row-control"><span>@include('admin.partials.audit-actor', ['actor' => $page->reviewRequestedByUser])</span></div>
                                </div>
                                <div class="wb-settings-row">
                                    <div class="wb-settings-row-label"><strong>Created</strong></div>
                                    <div class="wb-settings-row-control"><span>{{ $page->created_at?->format('Y-m-d H:i') }}</span></div>
                                </div>
                                <div class="wb-settings-row">
                                    <div class="wb-settings-row-label"><strong>Updated</strong></div>
                                    <div class="wb-settings-row-control"><span>{{ $page->updated_at?->format('Y-m-d H:i') }}</span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="wb-modal-footer wb-flex wb-items-center wb-gap-3 wb-flex-wrap">
                <a href="{{ route('admin.pages.edit', ['page' => $page, 'return_url' => $pageReturnUrl]) }}" class="wb-btn wb-btn-primary">Edit Page</a>
                @if ($page->isPublished() && $defaultPublicUrl)
                    <a href="{{ $defaultPublicUrl }}" target="_blank" rel="noopener noreferrer" class="wb-btn wb-btn-secondary">Open Public Page</a>
                @endif
                <a href="{{ $closeUrl }}" class="wb-btn wb-btn-secondary">Close</a>
            </div>
        </div>
    </div>
</div>
