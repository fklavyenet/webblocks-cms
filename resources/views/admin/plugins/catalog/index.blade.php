@php
    $pluginCatalogLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale();
    $pluginCatalogText = fn (string $key, array $replace = []) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->admin('plugin_catalog.'.$key, $pluginCatalogLocale, $replace);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $pluginCatalogText('title'), 'heading' => $pluginCatalogText('title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $pluginCatalogText('title'),
        'description' => $pluginCatalogText('description'),
        'actions' => '<a href="'.e(route('admin.system.plugins.index')).'" class="wb-btn wb-btn-secondary">'.e($pluginCatalogText('back_to_plugins')).'</a>',
        'context' => '<span class="wb-text-sm wb-text-muted">'.e($pluginCatalogText('context')).'</span>',
    ])

    @if (! $catalog->available)
        <div class="wb-alert wb-alert-danger wb-mb-4">
            {{ $catalog->message ?? $pluginCatalogText('unavailable') }}
        </div>
    @endif

    @if (count($catalog->plugins) === 0)
        <div class="wb-card">
            <div class="wb-card-body">
                <div class="wb-empty">
                    <div class="wb-empty-title">{{ $pluginCatalogText('empty_title') }}</div>
                    <div class="wb-empty-text">{{ $pluginCatalogText('empty_text') }}</div>
                </div>
            </div>
        </div>
    @else
        <div class="wb-grid wb-grid-auto-lg wb-gap-4">
            @foreach ($catalog->plugins as $plugin)
                @php
                    $compatibility = $plugin->compatibilityStatus ?? ($plugin->latestCompatibleRelease ? 'compatible' : 'unknown');
                    $compatibilityClass = match ($compatibility) {
                        'compatible', 'supported' => 'wb-status-active',
                        'incompatible', 'unsupported' => 'wb-status-danger',
                        default => 'wb-status-pending',
                    };
                    $price = $plugin->pricingType === 'paid'
                        ? ($plugin->priceMinor !== null ? trim(($plugin->priceCurrency ?? '').' '.number_format($plugin->priceMinor / 100, 2)) : $pluginCatalogText('not_listed'))
                        : $pluginCatalogText('free');
                @endphp
                <article class="wb-card">
                    @if ($plugin->artworkUrl)
                        <a href="{{ route('admin.plugins.catalog.show', $plugin->handle) }}" class="wb-media">
                            <img class="wb-media-img" src="{{ $plugin->artworkUrl }}" alt="{{ $plugin->artworkAlt ?? '' }}" loading="lazy">
                        </a>
                    @endif
                    <div class="wb-card-body wb-stack wb-gap-3">
                        <div class="wb-cluster wb-cluster-between wb-cluster-2">
                            <h2 class="wb-card-title"><a href="{{ route('admin.plugins.catalog.show', $plugin->handle) }}">{{ $plugin->label }}</a></h2>
                            <span class="wb-badge {{ $plugin->pricingType === 'paid' ? 'wb-badge-primary' : 'wb-badge-success' }}">{{ $price }}</span>
                        </div>
                        <p class="wb-m-0">{{ $plugin->summary ?? $pluginCatalogText('not_listed') }}</p>
                        @if (count($plugin->categories) > 0)
                            <div class="wb-cluster wb-cluster-2">
                                @foreach ($plugin->categories as $category)
                                    <span class="wb-badge">{{ $category['name'] }}</span>
                                @endforeach
                            </div>
                        @endif
                        <div class="wb-cluster wb-cluster-between wb-cluster-2 wb-text-sm">
                            <span class="wb-status {{ $compatibilityClass }}">{{ ucfirst($compatibility) }}</span>
                            <span class="wb-text-muted">{{ $pluginCatalogText('downloads_count', ['count' => number_format($plugin->downloadsTotal)]) }}</span>
                        </div>
                        <div class="wb-text-sm wb-text-muted">
                            {{ $pluginCatalogText('latest_release', ['version' => $plugin->latestCompatibleRelease?->version ?? $pluginCatalogText('not_listed')]) }}
                        </div>
                    </div>
                    <div class="wb-card-footer">
                        <a href="{{ route('admin.plugins.catalog.show', $plugin->handle) }}" class="wb-btn wb-btn-secondary wb-w-full">{{ $pluginCatalogText('view_plugin') }}</a>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection
