<section class="wb-card" id="theme-preview" aria-labelledby="theme-preview-title">
    <div class="wb-card-header">
        <h2 id="theme-preview-title">{{ $text('preview') }} · {{ $candidate->name }} · {{ $candidate->version }}</h2>
    </div>
    <div class="wb-card-body wb-stack wb-gap-3">
        @if (! $report['compatible'] || ! $report['intact'])
            <div class="wb-alert wb-alert-danger" role="alert">{{ $text($report['compatible'] ? 'damaged' : 'incompatible') }}</div>
        @endif
        @if ($report['affected_pages'])
            <div class="wb-alert wb-alert-warning" role="status">
                <strong>{{ $text('incompatible_site') }}</strong>
                <ul>
                    @foreach ($report['affected_pages'] as $affected)
                        <li>{{ $affected['title'] ?? '#'.$affected['id'] }} · {{ $affected['layout'] }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if (! $page)
            <p class="wb-text-muted">{{ $text('no_pages') }}</p>
        @else
            <form method="get" action="{{ route('admin.sites.themes.index', $site) }}#theme-preview" class="wb-cluster wb-flex-wrap wb-gap-3">
                <input type="hidden" name="version" value="{{ $candidate->id }}">
                <input type="hidden" name="page_list" value="{{ $pages->currentPage() }}">
                <div class="wb-field">
                    <label class="wb-label" for="theme-page">{{ $text('page') }}</label>
                    <select class="wb-select" id="theme-page" name="page">
                        @if (! $pages->contains('id', $page->id))
                            <option value="{{ $page->id }}" selected>{{ $page->title ?? '#'.$page->id }}</option>
                        @endif
                        @foreach ($pages as $listedPage)
                            <option value="{{ $listedPage->id }}" @selected($page->id === $listedPage->id)>{{ $listedPage->title ?? '#'.$listedPage->id }}</option>
                        @endforeach
                    </select>
                    @if ($pages->previousPageUrl())
                        <a href="{{ $pages->previousPageUrl() }}#theme-preview">{{ $text('previous_pages') }}</a>
                    @endif
                    @if ($pages->nextPageUrl())
                        <a href="{{ $pages->nextPageUrl() }}#theme-preview">{{ $text('next_pages') }}</a>
                    @endif
                </div>
                @if ($candidate->button_corners)
                    <div class="wb-field">
                        <label class="wb-label" for="theme-corners">{{ $text('button_corners') }}</label>
                        <select class="wb-select" id="theme-corners" name="button_corners">
                            @foreach (\WebBlocks\Cms\Support\Theme\PublicThemes::CORNERS as $corners)
                                <option value="{{ $corners }}" @selected($options['button_corners'] === $corners)>{{ $text($corners) }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div class="wb-field">
                    <label class="wb-label" for="theme-mode">{{ $text('mode') }}</label>
                    <select class="wb-select" id="theme-mode" name="mode">
                        @foreach (['light', 'dark', 'auto'] as $mode)
                            <option value="{{ $mode }}" @selected($options['mode'] === $mode)>{{ $text($mode) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="wb-field">
                    <label class="wb-label" for="theme-width">{{ $text('width') }}</label>
                    <select class="wb-select" id="theme-width" name="width">
                        @foreach (['desktop', 'phone'] as $width)
                            <option value="{{ $width }}" @selected($options['width'] === $width)>{{ $text($width) }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="wb-check">
                    <input type="checkbox" name="omit_site_css" value="1" @checked($options['omit_site_css'])>
                    <span>{{ $text('omit_css') }}</span>
                </label>
                <button type="submit" class="wb-btn wb-btn-secondary">{{ $text('preview') }}</button>
            </form>
            @foreach (['current', 'candidate'] as $side)
                @continue($side === 'candidate' && (! $report['compatible'] || ! $report['intact'] || ! in_array($page->publicShellPreset(), $candidate->layouts, true)))
                <section aria-label="{{ $text($side) }}">
                    <h3>{{ $text($side) }}</h3>
                    <div class="wb-editor-preview-viewport wb-theme-viewport" data-width="{{ $options['width'] }}">
                        <iframe title="{{ $text($side) }}" sandbox="allow-same-origin" src="{{ route('admin.sites.themes.preview', ['site' => $site, 'side' => $side] + $options) }}"></iframe>
                    </div>
                </section>
            @endforeach
        @endif
    </div>
    @if ($confirmation)
        <div class="wb-card-footer wb-cluster wb-cluster-end">
            <button type="button" class="wb-btn wb-btn-primary" data-wb-toggle="modal" data-wb-target="#theme-activate">{{ $text('activate') }}</button>
        </div>
    @endif
</section>
