@php
    $rawUrl = trim((string) ($block->url ?? ''));
    $parsedScheme = strtolower((string) parse_url($rawUrl, PHP_URL_SCHEME));
    $safeUrl = $rawUrl !== '' && in_array($parsedScheme, ['http', 'https'], true) ? $rawUrl : null;
    $query = trim((string) ($block->content ?: $safeUrl ?: ''));
    $openMapUrl = $query !== '' ? 'https://maps.google.com/?q='.rawurlencode($query) : null;
@endphp

@if ($block->title || $block->content || $block->url || $openMapUrl)
    <div class="wb-card wb-card-muted">
        <div class="wb-card-body wb-stack wb-gap-2">
            @if ($block->title)
                <strong>{{ $block->title }}</strong>
            @endif

            @if ($block->content)
                <p class="wb-m-0">{{ $block->content }}</p>
            @endif

            @if ($safeUrl && $safeUrl !== $block->content)
                <p class="wb-m-0">{{ $safeUrl }}</p>
            @endif

            @if ($openMapUrl)
                <a href="{{ $openMapUrl }}" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer">Open map</a>
            @endif
        </div>
    </div>
@endif

@if ($block->children->isNotEmpty())
    <div class="wb-stack wb-gap-4">
        @foreach ($block->children as $child)
            @include('pages.partials.block', ['block' => $child])
        @endforeach
    </div>
@endif
