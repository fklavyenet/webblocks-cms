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
    $publishedLabel = $publishedAt instanceof \Illuminate\Support\Carbon
        ? $publishedAt->format('Y-m-d H:i')
        : ($publishedAt ?: 'Not recorded');
    $reviewRequestedLabel = $reviewRequestedAt instanceof \Illuminate\Support\Carbon
        ? $reviewRequestedAt->format('Y-m-d H:i')
        : ($reviewRequestedAt ?: 'Not recorded');
@endphp

<div class="wb-overlay-layer wb-overlay-layer--dialog">
    <div class="wb-overlay-backdrop"></div>

    <div class="wb-modal wb-modal-lg is-open" id="{{ $drawerId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $drawerTitleId }}">
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
            <div class="wb-list wb-list-sm">
                <div class="wb-list-item">
                    <div class="wb-list-item-text">
                        <span class="wb-list-item-title">ID</span>
                        <span class="wb-list-item-sub">{{ $page->id }}</span>
                    </div>
                </div>

                <div class="wb-list-item">
                    <div class="wb-list-item-text">
                        <span class="wb-list-item-title">Name</span>
                        <span class="wb-list-item-sub">{{ $page->title }}</span>
                    </div>
                </div>

                <div class="wb-list-item">
                    <div class="wb-list-item-text">
                        <span class="wb-list-item-title">Path</span>
                        <span class="wb-list-item-sub">{{ $defaultPublicPath ?? 'Missing' }}</span>
                    </div>
                </div>

                <div class="wb-list-item">
                    <div class="wb-list-item-text">
                        <span class="wb-list-item-title">Site</span>
                        <span class="wb-list-item-sub">{{ $page->site?->name }}</span>
                    </div>
                </div>

                <div class="wb-list-item">
                    <div class="wb-list-item-text">
                        <span class="wb-list-item-title">Default URL</span>
                        <span class="wb-list-item-sub">{{ $defaultPublicUrl ?? 'Missing' }}</span>
                    </div>
                </div>

                <div class="wb-list-item">
                    <div class="wb-list-item-text">
                        <span class="wb-list-item-title">Slug</span>
                        <span class="wb-list-item-sub">{{ $page->slug }}</span>
                    </div>
                </div>

                <div class="wb-list-item">
                    <div class="wb-list-item-text">
                        <span class="wb-list-item-title">Locales</span>
                        <span class="wb-list-item-sub">
                            @foreach ($page->translationStatusForSite() as $translationStatus)
                                {{ strtoupper($translationStatus['locale']->code) }}: {{ $translationStatus['translation']?->slug ?? 'Missing' }}{{ $translationStatus['public_path'] ? ' | '.$translationStatus['public_path'] : '' }}@if (! $loop->last); @endif
                            @endforeach
                        </span>
                    </div>
                </div>

                <div class="wb-list-item">
                    <div class="wb-list-item-text">
                        <span class="wb-list-item-title">Status</span>
                        <span class="wb-list-item-sub"><span class="wb-status-pill {{ $page->workflowBadgeClass() }}">{{ $page->workflowLabel() }}</span></span>
                    </div>
                </div>

                <div class="wb-list-item">
                    <div class="wb-list-item-text">
                        <span class="wb-list-item-title">Review Requested</span>
                        <span class="wb-list-item-sub">{{ $reviewRequestedLabel }}</span>
                    </div>
                </div>

                <div class="wb-list-item">
                    <div class="wb-list-item-text">
                        <span class="wb-list-item-title">Published</span>
                        <span class="wb-list-item-sub">{{ $publishedLabel }}</span>
                    </div>
                </div>

                <div class="wb-list-item">
                    <div class="wb-list-item-text">
                        <span class="wb-list-item-title">Slot count</span>
                        <span class="wb-list-item-sub">{{ $slotCount }}</span>
                    </div>
                </div>

                <div class="wb-list-item">
                    <div class="wb-list-item-text">
                        <span class="wb-list-item-title">Block count</span>
                        <span class="wb-list-item-sub">{{ $blockCount }}</span>
                    </div>
                </div>

                <div class="wb-list-item">
                    <div class="wb-list-item-text">
                        <span class="wb-list-item-title">Created</span>
                        <span class="wb-list-item-sub">{{ $page->created_at?->format('Y-m-d H:i') }}</span>
                    </div>
                </div>

                <div class="wb-list-item">
                    <div class="wb-list-item-text">
                        <span class="wb-list-item-title">Updated</span>
                        <span class="wb-list-item-sub">{{ $page->updated_at?->format('Y-m-d H:i') }}</span>
                    </div>
                </div>
            </div>

                </div>
            </div>

            <div class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                <div class="wb-cluster wb-cluster-2">
                    <a href="{{ route('admin.pages.edit', $page) }}" class="wb-btn wb-btn-primary">Edit Page</a>

                    @if ($page->isPublished() && $defaultPublicUrl)
                        <a href="{{ $defaultPublicUrl }}" target="_blank" rel="noopener noreferrer" class="wb-btn wb-btn-secondary">Open Public Page</a>
                    @endif
                </div>

                <a href="{{ $closeUrl }}" class="wb-btn wb-btn-secondary">Close</a>
            </div>
        </div>
    </div>
</div>
