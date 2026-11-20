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
        <div class="wb-grid wb-cms-plugin-catalog-grid">
            @foreach ($catalog->plugins as $plugin)
                @php
                    $compatibility = $plugin->compatibilityStatus ?? ($plugin->latestCompatibleRelease ? 'compatible' : 'unknown');
                    $compatibilityClass = match ($compatibility) {
                        'compatible', 'supported' => 'wb-status-active',
                        'incompatible', 'unsupported' => 'wb-status-error',
                        default => 'wb-status-pending',
                    };
                    $price = $plugin->pricingType === 'paid'
                        ? ($plugin->priceMinor !== null ? trim(($plugin->priceCurrency ?? '').' '.number_format($plugin->priceMinor / 100, 2)) : $pluginCatalogText('not_listed'))
                        : $pluginCatalogText('free');
                    $detailUrl = route('admin.plugins.catalog.show', $plugin->handle);
                @endphp
                <article class="wb-card wb-cms-plugin-catalog-card">
                    @if ($plugin->artworkUrl)
                        <a href="{{ $detailUrl }}" class="wb-card-media wb-card-media--aspect-wide">
                            <img src="{{ $plugin->artworkUrl }}" alt="{{ $plugin->artworkAlt ?: $plugin->label }}" loading="lazy">
                        </a>
                    @endif
                    <div class="wb-card-body wb-stack wb-stack-2">
                        <div class="wb-cluster wb-cluster-between wb-cluster-2 wb-cms-plugin-catalog-heading">
                            <h2 class="wb-card-title"><a href="{{ $detailUrl }}">{{ $plugin->label }}</a></h2>
                            <span class="wb-badge">{{ $price }}</span>
                        </div>
                        <p class="wb-m-0">{{ $plugin->summary ?? $pluginCatalogText('not_listed') }}</p>
                        @if (count($plugin->categories) > 0)
                            <div class="wb-cluster wb-cluster-2">
                                @foreach ($plugin->categories as $category)
                                    <span class="wb-badge">{{ $category['name'] }}</span>
                                @endforeach
                            </div>
                        @endif
                        <div class="wb-cluster wb-cluster-2 wb-text-sm wb-text-muted wb-cms-plugin-catalog-meta">
                            <span class="{{ $compatibilityClass }}">{{ ucfirst($compatibility) }}</span>
                            <span>{{ $pluginCatalogText('latest_release', ['version' => $plugin->latestCompatibleRelease?->version ?? $pluginCatalogText('not_listed')]) }}</span>
                            <span>{{ $pluginCatalogText('downloads_count', ['count' => number_format($plugin->downloadsTotal)]) }}</span>
                        </div>
                    </div>
                    <div class="wb-card-footer">
                        <a href="{{ $detailUrl }}" class="wb-btn wb-btn-secondary wb-w-full">{{ $pluginCatalogText('view_plugin') }}</a>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
@endsection
