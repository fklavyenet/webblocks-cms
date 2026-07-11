@php
    $translator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $localeCode = $locale?->code ?? ($publicLocaleCode ?? null);
    $searchTitle = $translator->public('search.title', $localeCode);
    $searchDescription = $translator->public('search.description_default', $localeCode);
@endphp

@extends('webblocks-cms::layouts.public', [
    'title' => $searchTitle,
    'metaDescription' => $searchDescription,
    'publicLocaleCode' => $localeCode,
])

@section('content')
    <div class="wb-container wb-container-lg wb-section wb-stack wb-gap-4">
        <header class="wb-content-header">
            <h1 class="wb-content-title">{{ $searchTitle }}</h1>
            <p class="wb-content-subtitle">{{ $searchDescription }}</p>
        </header>

        <form action="{{ $searchPath }}" method="GET" role="search" class="wb-stack wb-gap-3">
            <div class="wb-cluster wb-cluster-2 wb-items-end">
                <div class="wb-stack wb-gap-1 wb-flex-1">
                    <label for="public-search-query">{{ $translator->public('search.query_label', $localeCode) }}</label>
                    <input id="public-search-query" type="search" name="q" value="{{ $query }}" class="wb-input" placeholder="{{ $translator->public('search.query_placeholder', $localeCode) }}">
                </div>

                <button type="submit" class="wb-btn wb-btn-primary">{{ $translator->public('search.submit', $localeCode) }}</button>
            </div>
        </form>

        @if ($state === 'empty')
            <div class="wb-alert wb-alert-info">
                <div>{{ $translator->public('search.helper', $localeCode) }}</div>
            </div>
        @elseif ($state === 'short')
            <div class="wb-alert wb-alert-info">
                <div>{{ $translator->public('search.minimum_query_length', $localeCode, ['count' => $minimumLength]) }}</div>
            </div>
        @elseif ($state === 'no-results')
            <div class="wb-alert wb-alert-info">
                <div>{{ $translator->public('search.no_results', $localeCode, ['query' => $query]) }}</div>
            </div>
        @elseif ($results)
            <div class="wb-stack wb-gap-3">
                <div class="wb-rich-text"><p>{{ $translator->public($results->total() === 1 ? 'search.count' : 'search.count_plural', $localeCode, ['count' => $results->total(), 'query' => $query]) }}</p></div>

                <div class="wb-link-list">
                    @foreach ($results as $result)
                        <a href="{{ $result->url }}" class="wb-link-list-item">
                            <div class="wb-link-list-main">
                                <span class="wb-link-list-title">{{ $result->title }}</span>
                                <span class="wb-link-list-meta">{{ $result->url }}</span>
                            </div>

                            @if ($result->display_excerpt)
                                <div class="wb-link-list-desc">{{ $result->display_excerpt }}</div>
                            @endif
                        </a>
                    @endforeach
                </div>

                {{ $results->links() }}
            </div>
        @endif
    </div>
@endsection
