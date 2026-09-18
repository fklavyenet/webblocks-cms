@extends('webblocks-cms::layouts.admin', ['title' => $title, 'heading' => $title])

@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key, array $replace = []) => $adminTranslator->get('admin.plugin_catalog.show.'.$key, $adminLocale, $replace);
    $plugin = $catalog->plugin;
    $release = $plugin?->latestCompatibleRelease;
    $compatibility = $plugin?->compatibilityStatus ?? ($release ? 'compatible' : 'unknown');
    $compatibilityClass = match ($compatibility) {
        'compatible', 'supported' => 'wb-status-active',
        'incompatible', 'unsupported' => 'wb-status-danger',
        default => 'wb-status-pending',
    };
    $canInstallFromCatalog = $plugin?->hasInstallableArtifact() ?? false;
    $price = $plugin?->pricingType === 'paid'
        ? ($plugin?->priceMinor !== null ? trim(($plugin->priceCurrency ?? '').' '.number_format($plugin->priceMinor / 100, 2)) : $adminText('not_provided'))
        : $adminText('free');
    $billingPeriod = $plugin?->billingPeriod ? $adminText('billing_'.$plugin->billingPeriod) : null;
    $hasDownloadActivity = $plugin && collect($plugin->dailyDownloads)->sum('downloads') > 0;
    $hasReleaseNotes = $release && ($release->displaySummary() || count($release->highlights) > 0);
    $links = $plugin ? array_filter([
        $adminText('website') => $plugin->firstWebsiteUrl(),
        $adminText('documentation') => $plugin->firstDocumentationUrl(),
        $adminText('support') => $plugin->firstSupportUrl(),
    ]) : [];
    $chartDate = static fn (string $date): string => \Carbon\CarbonImmutable::parse($date)->locale($adminLocale)->isoFormat('D MMM');
@endphp

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $plugin?->label ?? $adminText('title'),
        'description' => $plugin?->summary ?? $adminText('description'),
        'actions' => '<a href="'.e(route('admin.plugins.catalog.index')).'" class="wb-btn wb-btn-secondary">'.e($adminText('back_to_catalog')).'</a>',
    ])

    @if (! $catalog->available || $plugin === null)
        <div class="wb-alert wb-alert-danger wb-mb-4">{{ $catalog->message ?? $adminText('unavailable_alert') }}</div>
        <div class="wb-card"><div class="wb-card-body"><div class="wb-empty">
            <div class="wb-empty-title">{{ $adminText('unavailable_title') }}</div>
            <div class="wb-empty-text">{!! $adminText('unavailable_text', ['handle' => '<code>'.e($handle).'</code>']) !!}</div>
        </div></div></div>
    @else
        <section class="wb-card wb-promo wb-promo--split">
            <div class="wb-card-body wb-promo-copy wb-stack wb-gap-3">
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <span class="wb-badge {{ $plugin->pricingType === 'paid' ? 'wb-badge-primary' : 'wb-badge-success' }}">{{ $price }}{{ $billingPeriod ? ' '.$billingPeriod : '' }}</span>
                    @if ($plugin->downloadsTotal > 0)
                        <span class="wb-badge wb-badge-info">{{ $adminText('downloads_count', ['count' => number_format($plugin->downloadsTotal)]) }}</span>
                    @endif
                    @foreach ($plugin->categories as $category)
                        <span class="wb-badge">{{ $category['name'] }}</span>
                    @endforeach
                </div>
                <h2 class="wb-promo-title">{{ $plugin->label }}</h2>
                @if ($plugin->summary)<p class="wb-promo-text">{{ $plugin->summary }}</p>@endif
            </div>
            @if ($plugin->artworkUrl)
                <figure class="wb-promo-media"><img src="{{ $plugin->artworkUrl }}" alt="{{ $plugin->artworkAlt ?? '' }}"></figure>
            @endif
        </section>

        <div class="wb-grid wb-grid-2 wb-gap-4">
            <section class="wb-card">
                <div class="wb-card-header"><h2 class="wb-card-title">{{ $adminText('compatibility') }}</h2></div>
                <div class="wb-card-body wb-stack wb-gap-3">
                    <div><span class="wb-status {{ $compatibilityClass }}">{{ ucfirst($compatibility) }}</span></div>
                    <div class="wb-grid wb-grid-2">
                        <div><strong>{{ $adminText('latest_compatible_release') }}</strong><div>{{ $release?->version ?? $adminText('not_provided') }}</div></div>
                        <div><strong>{{ $adminText('local_state') }}</strong><div>{{ $installedState['installed'] ? $adminText('installed') : $adminText('not_installed') }}</div></div>
                    </div>
                    @if ($errors->has('catalog_install'))
                        <div class="wb-alert wb-alert-danger">{{ $errors->first('catalog_install') }}</div>
                    @endif
                    @if ($installedState['installed'])
                        <a href="{{ route('admin.system.plugins.show', $plugin->handle) }}" class="wb-btn wb-btn-primary">{{ $adminText('installed') }}</a>
                    @elseif ($canInstallFromCatalog)
                        <form method="POST" action="{{ route('admin.plugins.catalog.install', $plugin->handle) }}">
                            @csrf
                            <button type="submit" class="wb-btn wb-btn-primary"><i class="wb-icon wb-icon-package" aria-hidden="true"></i>{{ $adminText('install_from_catalog') }}</button>
                        </form>
                        <p class="wb-text-sm wb-text-muted">{{ $adminText('install_step_disabled') }}</p>
                    @else
                        <button type="button" class="wb-btn wb-btn-primary" disabled><i class="wb-icon wb-icon-package" aria-hidden="true"></i>{{ $adminText('install_from_catalog') }}</button>
                        <p class="wb-text-sm wb-text-muted">{{ $adminText('no_artifact') }}</p>
                    @endif
                </div>
            </section>

            @if ($plugin->description || count($links) > 0)
                <section class="wb-card">
                    <div class="wb-card-header"><h2 class="wb-card-title">{{ $adminText('plugin_description') }}</h2></div>
                    <div class="wb-card-body wb-stack wb-gap-3">
                        @if ($plugin->description)<p>{{ $plugin->description }}</p>@endif
                        @if (count($links) > 0)
                            <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                                @foreach ($links as $label => $url)
                                    <a href="{{ $url }}" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer">{{ $label }}</a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </section>
            @endif
        </div>

        @if ($hasDownloadActivity)
            <section class="wb-card">
                <div class="wb-card-header"><h2 class="wb-card-title">{{ $adminText('download_activity') }}</h2></div>
                <div class="wb-card-body wb-stack wb-gap-3">
                    <p class="wb-text-muted">{{ $adminText('download_activity_help', ['count' => number_format($plugin->downloadsTotal)]) }}</p>
                    <div class="wb-chart" data-wb-chart="line" aria-label="{{ $adminText('download_activity') }}" lang="{{ $adminLocale }}" data-wb-chart-help="{{ $adminText('chart_help') }}" data-wb-chart-empty="{{ $adminText('chart_empty') }}" data-wb-chart-error="{{ $adminText('chart_error') }}">
                        <p class="wb-chart-fallback wb-text-muted">{{ $adminText('chart_fallback') }}</p>
                        <div class="wb-table-wrap"><table class="wb-table">
                            <thead><tr><th scope="col">{{ $adminText('date') }}</th><th scope="col">{{ $adminText('downloads') }}</th></tr></thead>
                            <tbody>@foreach ($plugin->dailyDownloads as $day)<tr><th scope="row" data-wb-chart-label="{{ $chartDate($day['date']) }}">{{ $day['date'] }}</th><td data-wb-chart-value="{{ $day['downloads'] }}">{{ number_format($day['downloads']) }}</td></tr>@endforeach</tbody>
                        </table></div>
                    </div>
                </div>
            </section>
        @endif

        @if ($hasReleaseNotes)
            <section class="wb-card">
                <div class="wb-card-header"><h2 class="wb-card-title">{{ $adminText('release_notes') }}</h2></div>
                <div class="wb-card-body wb-stack wb-gap-3">
                    @if ($release->displaySummary())<p>{{ $release->displaySummary() }}</p>@endif
                    @if (count($release->highlights) > 0)<ul>@foreach ($release->highlights as $highlight)<li>{{ $highlight }}</li>@endforeach</ul>@endif
                </div>
            </section>
        @endif
    @endif
@endsection
