<nav class="wb-pagination" aria-label="{{ $translator->get('blocks.collection_pagination', $localeCode) }}">
    <ol class="wb-pagination-list">
        @if ($paginator->previousPageUrl())
            <li class="wb-pagination-item"><a class="wb-pagination-link" rel="prev" href="{{ $paginator->previousPageUrl() }}">{{ $translator->get('blocks.collection_previous', $localeCode) }}</a></li>
        @endif
        <li class="wb-pagination-item"><span class="wb-pagination-link" aria-current="page">{{ $translator->get('blocks.collection_page', $localeCode, ['current' => $paginator->currentPage(), 'last' => $paginator->lastPage()]) }}</span></li>
        @if ($paginator->nextPageUrl())
            <li class="wb-pagination-item"><a class="wb-pagination-link" rel="next" href="{{ $paginator->nextPageUrl() }}">{{ $translator->get('blocks.collection_next', $localeCode) }}</a></li>
        @endif
    </ol>
</nav>
