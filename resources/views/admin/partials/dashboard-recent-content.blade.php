@php
    $showRecentPages = $dashboardAccess['pages'];
    $showRecentMedia = $dashboardAccess['media'];
    $hasContentTabs = $showRecentPages && $showRecentMedia;
@endphp
@if ($showRecentPages || $showRecentMedia)
    <section class="wb-card wb-tabs wb-tabs-pills wb-cms-dashboard-content" @if ($hasContentTabs) data-wb-tabs @endif aria-labelledby="dashboard-content-title">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <h2 class="wb-card-title" id="dashboard-content-title">{{ $adminText('dashboard.recent_content') }}</h2>
            @if ($hasContentTabs)
                <div class="wb-tabs-nav" role="tablist" aria-labelledby="dashboard-content-title">
                    <button type="button" id="dashboard-pages-tab" class="wb-tabs-btn is-active" data-wb-tab="dashboard-recent-pages" role="tab" aria-controls="dashboard-recent-pages" aria-selected="true">{{ $adminText('dashboard.pages') }}</button>
                    <button type="button" id="dashboard-media-tab" class="wb-tabs-btn" data-wb-tab="dashboard-recent-media" role="tab" aria-controls="dashboard-recent-media" aria-selected="false" tabindex="-1">{{ $adminText('dashboard.media') }}</button>
                </div>
            @endif
        </div>
        <div class="wb-card-body">
            @if ($showRecentPages)
                <div id="dashboard-recent-pages" @if ($hasContentTabs) class="wb-tabs-panel is-active" role="tabpanel" aria-labelledby="dashboard-pages-tab" @endif>
                    @if ($recentPages->isEmpty())
                        <p class="wb-text-sm wb-text-muted">{{ $adminText('dashboard.no_pages') }}</p>
                    @else
                        <div class="wb-link-list">
                            @foreach ($recentPages as $page)
                                @php
                                    $pageTitle = trim((string) $page->title);
                                    $pageSlug = trim((string) $page->slug);
                                    if ($pageTitle === '') {
                                        $translation = $page->translations->sortBy('id')->first(fn ($translation) => filled($translation->name));
                                        $pageTitle = $translation?->name ?? '#'.$page->id;
                                        $pageSlug = $translation?->slug ?? $pageSlug;
                                    }
                                @endphp
                                <a href="{{ route('admin.pages.edit', $page) }}" class="wb-link-list-item wb-cms-dashboard-recent-page">
                                    <div class="wb-link-list-main">
                                        <div class="wb-link-list-title" title="{{ $pageTitle }}">{{ $pageTitle }}</div>
                                        @if ($pageSlug !== '')
                                            <div class="wb-link-list-meta" title="{{ $pageSlug }}">{{ $pageSlug }}</div>
                                        @endif
                                    </div>
                                    <span class="wb-status-pill {{ $page->workflowBadgeClass() }}">{{ $page->workflowLabel() }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                    <div class="wb-cms-dashboard-content-link"><a href="{{ route('admin.pages.index') }}" class="wb-btn wb-btn-secondary wb-btn-sm">{{ $adminText('dashboard.view_all') }} <i class="wb-icon wb-icon-arrow-right" aria-hidden="true"></i></a></div>
                </div>
            @endif
            @if ($showRecentMedia)
                <div id="dashboard-recent-media" @if ($hasContentTabs) class="wb-tabs-panel" role="tabpanel" aria-labelledby="dashboard-media-tab" hidden @endif>
                    @if ($recentAssets->isEmpty())
                        <p class="wb-text-sm wb-text-muted">{{ $adminText('dashboard.no_media') }}</p>
                    @else
                        <div class="wb-link-list">
                            @foreach ($recentAssets as $asset)
                                <a href="{{ route('admin.media.edit', $asset) }}" class="wb-link-list-item">
                                    <div class="wb-link-list-main"><div class="wb-link-list-title" title="{{ $asset->title ?: $asset->original_name }}">{{ $asset->title ?: $asset->original_name }}</div><div class="wb-link-list-meta">{{ $adminTranslator->getOrDefault('admin.media_index.kind_names.'.$asset->kind, $asset->kind, $adminLocaleCode) }} · {{ $asset->humanSize() }}</div></div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                    <div class="wb-cms-dashboard-content-link"><a href="{{ route('admin.media.index') }}" class="wb-btn wb-btn-secondary wb-btn-sm">{{ $adminText('dashboard.manage') }} <i class="wb-icon wb-icon-arrow-right" aria-hidden="true"></i></a></div>
                </div>
            @endif
        </div>
    </section>
@endif
