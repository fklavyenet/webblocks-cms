@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $pageDetailsLocale = app(AdminLocaleResolver::class)->locale();
    $pageDetailsTranslator = app(CmsTranslator::class);
    $pageDetailsText = static fn (string $key, array $replace = []) => $pageDetailsTranslator->admin('page_details_modal.'.$key, $pageDetailsLocale, $replace);
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
        : ($publishedAt ?: $pageDetailsText('not_recorded'));
    $reviewRequestedLabel = $reviewRequestedAt instanceof \Illuminate\Support\Carbon
        ? $reviewRequestedAt->format('Y-m-d H:i')
        : ($reviewRequestedAt ?: $pageDetailsText('not_recorded'));
    $localeSummaries = collect($page->translationStatusForSite())
        ->map(fn (array $translationStatus) => strtoupper($translationStatus['locale']->code).': '.($translationStatus['translation']?->slug ?? $pageDetailsText('missing')).($translationStatus['public_path'] ? ' | '.$translationStatus['public_path'] : ''));
@endphp

<div class="wb-overlay-layer wb-overlay-layer--dialog">
    <div class="wb-overlay-backdrop"></div>

    <div class="wb-modal wb-modal-xl is-open" id="{{ $drawerId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $drawerTitleId }}">
        <div class="wb-modal-dialog">
            <div class="wb-modal-header">
                <div class="wb-stack wb-gap-1">
                    <h2 class="wb-modal-title" id="{{ $drawerTitleId }}">{{ $pageDetailsText('title') }}</h2>
                    <span class="wb-text-sm wb-text-muted">{{ $pageDetailsText('description') }}</span>
                </div>
                <a href="{{ $closeUrl }}" class="wb-modal-close" aria-label="{{ $pageDetailsText('close_aria') }}">
                    <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                </a>
            </div>

            <div class="wb-modal-body">
                <div class="wb-stack wb-gap-4">
                    <div class="wb-grid wb-grid-2">
                        <div class="wb-card">
                            <div class="wb-card-header"><strong>{{ $pageDetailsText('page') }}</strong></div>
                            <div class="wb-card-body">
                                <div class="wb-table-wrap">
                                    <table class="wb-table wb-table-striped wb-table-hover wb-text-sm">
                                        <tbody>
                                            <tr>
                                                <th scope="row" class="wb-table-key">{{ $pageDetailsText('id') }}</th>
                                                <td>{{ $page->id }}</td>
                                            </tr>
                                            <tr>
                                                <th scope="row" class="wb-table-key">{{ $pageDetailsText('name') }}</th>
                                                <td>{{ $page->title }}</td>
                                            </tr>
                                            <tr>
                                                <th scope="row" class="wb-table-key">{{ $pageDetailsText('slug') }}</th>
                                                <td><code>{{ $page->slug }}</code></td>
                                            </tr>
                                            <tr>
                                                <th scope="row" class="wb-table-key">{{ $pageDetailsText('path') }}</th>
                                                <td><code>{{ $defaultPublicPath ?? $pageDetailsText('missing') }}</code></td>
                                            </tr>
                                            <tr>
                                                <th scope="row" class="wb-table-key">{{ $pageDetailsText('site') }}</th>
                                                <td>{{ $page->site?->name }}</td>
                                            </tr>
                                            <tr>
                                                <th scope="row" class="wb-table-key">{{ $pageDetailsText('default_url') }}</th>
                                                <td><code>{{ $defaultPublicUrl ?? $pageDetailsText('missing') }}</code></td>
                                            </tr>
                                            <tr>
                                                <th scope="row" class="wb-table-key">{{ $pageDetailsText('locales') }}</th>
                                                <td>
                                                    <div class="wb-stack wb-gap-1">
                                                        @foreach ($localeSummaries as $localeSummary)
                                                            <span>{{ $localeSummary }}</span>
                                                        @endforeach
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row" class="wb-table-key">{{ $pageDetailsText('slot_count') }}</th>
                                                <td>{{ $slotCount }}</td>
                                            </tr>
                                            <tr>
                                                <th scope="row" class="wb-table-key">{{ $pageDetailsText('block_count') }}</th>
                                                <td>{{ $blockCount }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="wb-card">
                            <div class="wb-card-header"><strong>{{ $pageDetailsText('status_audit') }}</strong></div>
                            <div class="wb-card-body">
                                <div class="wb-table-wrap">
                                    <table class="wb-table wb-table-striped wb-table-hover wb-text-sm">
                                        <tbody>
                                            <tr>
                                                <th scope="row" class="wb-table-key">{{ $pageDetailsText('status') }}</th>
                                                <td><span class="wb-status-pill {{ $page->workflowBadgeClass() }}">{{ $page->workflowLabel() }}</span></td>
                                            </tr>
                                            <tr>
                                                <th scope="row" class="wb-table-key">{{ $pageDetailsText('review_requested') }}</th>
                                                <td>{{ $reviewRequestedLabel }}</td>
                                            </tr>
                                            <tr>
                                                <th scope="row" class="wb-table-key">{{ $pageDetailsText('published') }}</th>
                                                <td>{{ $publishedLabel }}</td>
                                            </tr>
                                            <tr>
                                                <th scope="row" class="wb-table-key">{{ $pageDetailsText('created_by') }}</th>
                                                <td>@include('webblocks-cms::admin.partials.audit-actor', ['actor' => $page->createdByUser])</td>
                                            </tr>
                                            <tr>
                                                <th scope="row" class="wb-table-key">{{ $pageDetailsText('last_edited_by') }}</th>
                                                <td>@include('webblocks-cms::admin.partials.audit-actor', ['actor' => $page->updatedByUser])</td>
                                            </tr>
                                            <tr>
                                                <th scope="row" class="wb-table-key">{{ $pageDetailsText('published_by') }}</th>
                                                <td>@include('webblocks-cms::admin.partials.audit-actor', ['actor' => $page->publishedByUser])</td>
                                            </tr>
                                            <tr>
                                                <th scope="row" class="wb-table-key">{{ $pageDetailsText('archived_by') }}</th>
                                                <td>@include('webblocks-cms::admin.partials.audit-actor', ['actor' => $page->archivedByUser])</td>
                                            </tr>
                                            <tr>
                                                <th scope="row" class="wb-table-key">{{ $pageDetailsText('review_requested_by') }}</th>
                                                <td>@include('webblocks-cms::admin.partials.audit-actor', ['actor' => $page->reviewRequestedByUser])</td>
                                            </tr>
                                            <tr>
                                                <th scope="row" class="wb-table-key">{{ $pageDetailsText('created') }}</th>
                                                <td>{{ $page->created_at?->format('Y-m-d H:i') }}</td>
                                            </tr>
                                            <tr>
                                                <th scope="row" class="wb-table-key">{{ $pageDetailsText('updated') }}</th>
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
                    <a href="{{ route('admin.pages.edit', ['page' => $page, 'return_url' => $pageReturnUrl]) }}" class="wb-btn wb-btn-primary">{{ $pageDetailsText('edit_page') }}</a>
                    @if ($page->isPublished() && $defaultPublicUrl)
                        <a href="{{ $defaultPublicUrl }}" target="_blank" rel="noopener noreferrer" class="wb-btn wb-btn-secondary">{{ $pageDetailsText('open_public_page') }}</a>
                    @endif
                    <a href="{{ $closeUrl }}" class="wb-btn wb-btn-secondary">{{ $pageDetailsText('close') }}</a>
                </div>
            </div>
        </div>
    </div>
</div>
