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
                            <div class="wb-card-body">
                                <div class="wb-table-wrap">
                                    <table class="wb-table wb-table-striped wb-table-hover wb-text-sm">
                                        <tbody>
                                            <tr>
                                                <th scope="row">ID</th>
                                                <td>{{ $page->id }}</td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Name</th>
                                                <td>{{ $page->title }}</td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Slug</th>
                                                <td><code>{{ $page->slug }}</code></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Path</th>
                                                <td><code>{{ $defaultPublicPath ?? 'Missing' }}</code></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Site</th>
                                                <td>{{ $page->site?->name }}</td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Default URL</th>
                                                <td><code>{{ $defaultPublicUrl ?? 'Missing' }}</code></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Locales</th>
                                                <td>
                                                    <div class="wb-stack wb-gap-1">
                                                        @foreach ($localeSummaries as $localeSummary)
                                                            <span>{{ $localeSummary }}</span>
                                                        @endforeach
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Slot count</th>
                                                <td>{{ $slotCount }}</td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Block count</th>
                                                <td>{{ $blockCount }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="wb-card">
                            <div class="wb-card-header"><strong>Status &amp; Audit</strong></div>
                            <div class="wb-card-body">
                                <div class="wb-table-wrap">
                                    <table class="wb-table wb-table-striped wb-table-hover wb-text-sm">
                                        <tbody>
                                            <tr>
                                                <th scope="row">Status</th>
                                                <td><span class="wb-status-pill {{ $page->workflowBadgeClass() }}">{{ $page->workflowLabel() }}</span></td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Review Requested</th>
                                                <td>{{ $reviewRequestedLabel }}</td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Published</th>
                                                <td>{{ $publishedLabel }}</td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Created by</th>
                                                <td>@include('webblocks-cms::admin.partials.audit-actor', ['actor' => $page->createdByUser])</td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Last edited by</th>
                                                <td>@include('webblocks-cms::admin.partials.audit-actor', ['actor' => $page->updatedByUser])</td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Published by</th>
                                                <td>@include('webblocks-cms::admin.partials.audit-actor', ['actor' => $page->publishedByUser])</td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Archived by</th>
                                                <td>@include('webblocks-cms::admin.partials.audit-actor', ['actor' => $page->archivedByUser])</td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Review requested by</th>
                                                <td>@include('webblocks-cms::admin.partials.audit-actor', ['actor' => $page->reviewRequestedByUser])</td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Created</th>
                                                <td>{{ $page->created_at?->format('Y-m-d H:i') }}</td>
                                            </tr>
                                            <tr>
                                                <th scope="row">Updated</th>
                                                <td>{{ $page->updated_at?->format('Y-m-d H:i') }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                <div class="wb-flex wb-items-center wb-gap-3 wb-flex-wrap">
                    <a href="{{ route('admin.pages.edit', ['page' => $page, 'return_url' => $pageReturnUrl]) }}" class="wb-btn wb-btn-primary">Edit Page</a>
                    @if ($page->isPublished() && $defaultPublicUrl)
                        <a href="{{ $defaultPublicUrl }}" target="_blank" rel="noopener noreferrer" class="wb-btn wb-btn-secondary">Open Public Page</a>
                    @endif
                    <a href="{{ $closeUrl }}" class="wb-btn wb-btn-secondary">Close</a>
                </div>
            </div>
        </div>
    </div>
</div>
