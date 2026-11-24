@extends('webblocks-cms::layouts.admin', ['title' => $title, 'heading' => $title])

@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key, array $replace = []) => $adminTranslator->get('admin.plugin_catalog.show.'.$key, $adminLocale, $replace);
    $plugin = $catalog->plugin;
    $release = $plugin?->latestCompatibleRelease;
    $compatibility = $plugin?->compatibilityStatus ?? ($release ? 'compatible' : 'unknown');
    [$compatibilityLabel, $compatibilityClass] = match ($compatibility) {
        'compatible', 'supported' => ['compatible', 'wb-status-active'],
        'incompatible', 'unsupported' => ['incompatible', 'wb-status-error'],
        default => ['unknown_compatibility', 'wb-status-pending'],
    };
    $canInstallFromCatalog = $plugin?->hasInstallableArtifact() ?? false;
    $price = $plugin?->pricingType === 'paid'
        ? ($plugin?->priceMinor !== null ? trim(($plugin->priceCurrency ?? '').' '.number_format($plugin->priceMinor / 100, 2)) : $adminText('not_provided'))
        : $adminText('free');
    $billingPeriod = $plugin?->billingPeriod ? $adminText('billing_'.$plugin->billingPeriod) : null;
    $description = $plugin?->description ?? $plugin?->summary;
    $documentation = $plugin?->firstDocumentationUrl();
    $releaseSummary = $release?->displaySummary();
    $releaseHighlights = $release?->highlights ?? [];
    $links = $plugin ? array_filter([
        $adminText('website') => $plugin->firstWebsiteUrl(),
        $adminText('support') => $plugin->firstSupportUrl(),
    ]) : [];
    $slides = $plugin?->screenshots ?? [];
    if ($plugin?->artworkUrl !== null) {
        array_unshift($slides, [
            'url' => $plugin->artworkUrl,
            'alt' => $plugin->artworkAlt,
            'caption' => null,
            'width' => null,
            'height' => null,
        ]);
    }
    $sliderText = fn (string $key) => $adminTranslator->get('blocks.a11y.'.$key, $adminLocale);
@endphp

@section('content')
    @if (! $catalog->available || $plugin === null)
        @include('webblocks-cms::admin.partials.page-header', [
            'title' => $adminText('title'),
            'description' => $adminText('description'),
            'actions' => '<a href="'.e(route('admin.plugins.catalog.index')).'" class="wb-btn wb-btn-secondary">'.e($adminText('back_to_catalog')).'</a>',
        ])

        <div class="wb-alert wb-alert-danger wb-mb-4">{{ $catalog->message ?? $adminText('unavailable_alert') }}</div>
        <div class="wb-card"><div class="wb-card-body"><div class="wb-empty">
            <div class="wb-empty-title">{{ $adminText('unavailable_title') }}</div>
            <div class="wb-empty-text">{!! $adminText('unavailable_text', ['handle' => '<code>'.e($handle).'</code>']) !!}</div>
        </div></div></div>
    @else
        <div class="wb-cluster wb-cluster-end wb-mb-4">
            <a href="{{ route('admin.plugins.catalog.index') }}" class="wb-btn wb-btn-secondary">{{ $adminText('back_to_catalog') }}</a>
        </div>

        <section class="wb-card wb-promo wb-block wb-p-4" aria-labelledby="plugin-catalog-title">
            <div class="wb-card-body wb-row wb-row-gap-4 wb-items-start wb-p-0">
                <div class="{{ $slides === [] ? 'wb-col-12' : 'wb-col-md-5' }} wb-promo-copy wb-min-w-0">
                    <header class="wb-stack wb-stack-2">
                        <h1 class="wb-promo-title wb-text-3xl" id="plugin-catalog-title">{{ $plugin->label }}</h1>
                        @if ($description)
                            <p class="wb-promo-text wb-text-sm">{{ $description }}</p>
                        @endif
                    </header>

                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-sm" aria-label="{{ $adminText('installation') }}">
                            <tbody>
                                <tr>
                                    <th scope="row" class="wb-table-key">{{ $adminText('compatibility') }}</th>
                                    <td><span class="wb-status-pill {{ $compatibilityClass }}">{{ $adminText($compatibilityLabel) }}</span></td>
                                </tr>
                                @if ($release?->version !== null)
                                    <tr>
                                        <th scope="row" class="wb-table-key">{{ $adminText('latest_compatible_release') }}</th>
                                        <td>{{ $release->version }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <th scope="row" class="wb-table-key">{{ $adminText('local_state') }}</th>
                                    <td>{{ $installedState['installed'] ? $adminText('installed') : $adminText('not_installed') }}</td>
                                </tr>
                                <tr>
                                    <th scope="row" class="wb-table-key">{{ $adminText('price') }}</th>
                                    <td>{{ $price }}{{ $billingPeriod ? ' '.$billingPeriod : '' }}</td>
                                </tr>
                                <tr>
                                    <th scope="row" class="wb-table-key">{{ $adminText('downloads') }}</th>
                                    <td>{{ number_format($plugin->downloadsTotal) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="wb-action-group wb-gap-2">
                        @if ($installedState['installed'])
                            <a href="{{ route('admin.system.plugins.show', $plugin->handle) }}" class="wb-btn wb-btn-primary wb-btn-sm">{{ $adminText('manage_plugin') }}</a>
                        @elseif ($canInstallFromCatalog)
                            <form method="POST" action="{{ route('admin.plugins.catalog.install', $plugin->handle) }}">
                                @csrf
                                <button type="submit" class="wb-btn wb-btn-primary wb-btn-sm" aria-describedby="plugin-catalog-install-help">
                                    <i class="wb-icon wb-icon-package" aria-hidden="true"></i>{{ $adminText('install_from_catalog') }}
                                </button>
                            </form>
                        @else
                            <button type="button" class="wb-btn wb-btn-primary wb-btn-sm" aria-describedby="plugin-catalog-install-help" disabled>
                                <i class="wb-icon wb-icon-package" aria-hidden="true"></i>{{ $adminText('install_from_catalog') }}
                            </button>
                        @endif
                        @if ($documentation)
                            <a href="{{ $documentation }}" class="wb-btn wb-btn-secondary wb-btn-sm" target="_blank" rel="noopener noreferrer">{{ $adminText('documentation') }}</a>
                        @endif
                    </div>

                    @if (! $installedState['installed'])
                        <p id="plugin-catalog-install-help" class="wb-text-sm wb-text-muted">
                            {{ $adminText($canInstallFromCatalog ? 'install_step_disabled' : 'no_artifact') }}
                        </p>
                    @endif
                    @if ($errors->has('catalog_install'))
                        <div class="wb-alert wb-alert-danger" role="alert">{{ $errors->first('catalog_install') }}</div>
                    @endif
                    @if ($links !== [])
                        <div class="wb-cluster wb-cluster-2 wb-text-sm">
                            @foreach ($links as $label => $url)
                                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer">{{ $label }}</a>
                            @endforeach
                        </div>
                    @endif

                    @if ($releaseSummary || $releaseHighlights !== [])
                        <section class="wb-stack wb-stack-2" aria-labelledby="plugin-catalog-release-notes">
                            <h2 class="wb-card-title" id="plugin-catalog-release-notes">{{ $adminText('release_notes') }}</h2>
                            @if ($releaseSummary)
                                <p>{{ $releaseSummary }}</p>
                            @endif
                            @if ($releaseHighlights !== [])
                                <ul class="wb-marker-list">
                                    @foreach ($releaseHighlights as $highlight)
                                        <li>{{ $highlight }}</li>
                                    @endforeach
                                </ul>
                            @endif
                        </section>
                    @endif
                </div>

                @if ($slides !== [])
                    <div class="wb-col-md-7 wb-min-w-0">
                        <div class="wb-slider wb-slider-ratio-video wb-slider-height-auto wb-slider-overlay-none wb-border"
                             data-wb-slider data-wb-slider-autoplay="false" role="region" aria-label="{{ $sliderText('slides') }}">
                            <div class="wb-slider-viewport">
                                <div class="wb-slider-track">
                                    @foreach ($slides as $slide)
                                        <figure @class(['wb-slide', 'is-active' => $loop->first]) @unless ($loop->first) aria-hidden="true" @endunless>
                                            <a class="wb-slide-media" href="{{ $slide['url'] }}" target="_blank" rel="noopener noreferrer">
                                                <img class="wb-slide-media wb-slide-media-contain" src="{{ $slide['url'] }}" alt="{{ $slide['alt'] ?? $slide['caption'] ?? $plugin->label }}"
                                                     @if ($slide['width'] !== null && $slide['height'] !== null) width="{{ $slide['width'] }}" height="{{ $slide['height'] }}" @endif
                                                     @unless ($loop->first) loading="lazy" @endunless decoding="async" referrerpolicy="no-referrer">
                                            </a>
                                            @if ($slide['caption'] !== null && $slide['alt'] !== null && $slide['caption'] !== $slide['alt'])
                                                <figcaption class="wb-sr-only">{{ $slide['caption'] }}</figcaption>
                                            @endif
                                        </figure>
                                    @endforeach
                                </div>
                            </div>

                            @if (count($slides) > 1)
                                <div class="wb-slider-controls">
                                    <button type="button" class="wb-btn wb-btn-icon wb-btn-sm wb-slider-arrow wb-slider-prev" data-wb-slider-prev data-wb-tooltip="{{ $sliderText('previous_slide') }}" aria-label="{{ $sliderText('previous_slide') }}">
                                        <i class="wb-icon wb-icon-chevron-left" aria-hidden="true"></i>
                                    </button>
                                    <button type="button" class="wb-btn wb-btn-icon wb-btn-sm wb-slider-arrow wb-slider-next" data-wb-slider-next data-wb-tooltip="{{ $sliderText('next_slide') }}" aria-label="{{ $sliderText('next_slide') }}">
                                        <i class="wb-icon wb-icon-chevron-right" aria-hidden="true"></i>
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        </section>
    @endif
@endsection
