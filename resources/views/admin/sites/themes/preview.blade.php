@php
    $text = static fn (string $key) => __('webblocks-cms::themes.'.$key);
    $title = __('webblocks-cms::themes.preview_heading', ['name' => $candidate->name, 'version' => $candidate->version]);
    $preset = $site->resolvedPublicThemePreset();
    $label = static fn ($change) => $change?->theme_version_id ? $change->name.' · '.$change->version : $text('builtin').' · '.str($preset)->headline();
    $back = '<a class="wb-btn wb-btn-secondary" href="'.e(route('admin.sites.themes.index', $site)).'">'.e($text('back_to_themes')).'</a>';

    $select = static fn (string $name, array $choices) => [
        'id' => 'theme-'.$name, 'name' => $name, 'label' => $text($name),
        'selected' => $options[$name], 'placeholder' => null, 'options' => $choices,
    ];
    $pageChoices = $pages->getCollection()->mapWithKeys(fn ($listedPage) => [$listedPage->id => $listedPage->title ?? '#'.$listedPage->id])->all();
    if ($page && ! array_key_exists($page->id, $pageChoices)) {
        $pageChoices = [$page->id => $page->title ?? '#'.$page->id] + $pageChoices;
    }
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $title, 'heading' => $title])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', ['title' => $title, 'actions' => $back])
    @include('webblocks-cms::admin.system.themes.feedback')

    <div class="wb-stack wb-gap-4">
        @if ($page)
            <div class="wb-card wb-card-muted">
                <div class="wb-card-body">
                    @include('webblocks-cms::admin.partials.listing-filters', [
                        'action' => route('admin.sites.themes.comparison', ['site' => $site, 'version' => $candidate->id]),
                        'hidden' => ['page_list' => $pages->currentPage(), 'locale' => $options['locale']],
                        'selects' => [
                            $select('page', $pageChoices),
                            ...($candidate->button_corners ? [$select('button_corners', ['default' => $text('default'), 'sharp' => $text('sharp'), 'soft' => $text('soft')])] : []),
                            $select('mode', ['light' => $text('light'), 'dark' => $text('dark'), 'auto' => $text('auto')]),
                            $select('width', ['desktop' => $text('desktop'), 'phone' => $text('phone')]),
                        ],
                        'checkboxes' => [
                            ['name' => 'omit_site_css', 'value' => 1, 'checked' => $options['omit_site_css'], 'label' => $text('omit_css')],
                        ],
                    ])
                    @if ($pages->previousPageUrl() || $pages->nextPageUrl())
                        <div class="wb-cluster wb-gap-3 wb-mt-2">
                            @if ($pages->previousPageUrl())
                                <a href="{{ $pages->previousPageUrl() }}">{{ $text('previous_pages') }}</a>
                            @endif
                            @if ($pages->nextPageUrl())
                                <a href="{{ $pages->nextPageUrl() }}">{{ $text('next_pages') }}</a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        @endif

        <section class="wb-card" aria-label="{{ $text('preview') }}">
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
                @if ($page)
                    <div class="wb-theme-comparison" data-width="{{ $options['width'] }}">
                        @foreach (['current', 'candidate'] as $side)
                            @continue($side === 'candidate' && (! $report['compatible'] || ! $report['intact'] || ! in_array($page->publicShellPreset(), $candidate->layouts, true)))
                            <section class="wb-card" aria-label="{{ $text($side) }}">
                                <div class="wb-card-header"><strong>{{ $text($side) }}</strong></div>
                                <div class="wb-editor-preview-viewport wb-theme-viewport">
                                    <iframe title="{{ $text($side) }}" sandbox="allow-same-origin" src="{{ route('admin.sites.themes.preview', ['site' => $site, 'side' => $side] + $options) }}"></iframe>
                                </div>
                            </section>
                        @endforeach
                    </div>
                @else
                    <p class="wb-text-muted">{{ $text('no_pages') }}</p>
                @endif
            </div>
            @if ($confirmation)
                <div class="wb-card-footer wb-cluster wb-cluster-end">
                    <button type="button" class="wb-btn wb-btn-primary" data-wb-toggle="modal" data-wb-target="#theme-activate">{{ $text('activate') }}</button>
                </div>
            @endif
        </section>
    </div>
@endsection

@push('overlays')
    @if ($confirmation)
        @include('webblocks-cms::admin.sites.themes.confirm', [
            'id' => 'theme-activate', 'operation' => 'activate', 'description' => $text('activate_help'),
            'target' => $candidate->name.' · '.$candidate->version,
        ])
    @endif
@endpush
