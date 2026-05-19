@extends('webblocks-cms::layouts.public', [
    'title' => 'Search',
    'metaDescription' => 'Public site search results.',
])

@section('content')
    <div class="wb-container wb-container-lg wb-section wb-stack wb-gap-4">
        <header class="wb-content-header">
            <h1 class="wb-content-title">Search</h1>
            <p class="wb-content-subtitle">Search published content for the current site and locale.</p>
        </header>

        <form action="{{ $searchPath }}" method="GET" role="search" class="wb-stack wb-gap-3">
            <div class="wb-cluster wb-cluster-2 wb-items-end">
                <div class="wb-stack wb-gap-1 wb-flex-1">
                    <label for="public-search-query">Search query</label>
                    <input id="public-search-query" type="search" name="q" value="{{ $query }}" class="wb-input" placeholder="Search published pages">
                </div>

                <button type="submit" class="wb-btn wb-btn-primary">Search</button>
            </div>
        </form>

        @if ($state === 'empty')
            <div class="wb-alert wb-alert-info">
                <div>Enter a search term to find published content for this site and locale.</div>
            </div>
        @elseif ($state === 'short')
            <div class="wb-alert wb-alert-info">
                <div>Enter at least {{ $minimumLength }} characters to search.</div>
            </div>
        @elseif ($state === 'no-results')
            <div class="wb-alert wb-alert-info">
                <div>No results matched <strong>{{ $query }}</strong>.</div>
            </div>
        @elseif ($results)
            <div class="wb-stack wb-gap-3">
                <div class="wb-rich-text"><p>{{ $results->total() }} result{{ $results->total() === 1 ? '' : 's' }} for <strong>{{ $query }}</strong>.</p></div>

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
