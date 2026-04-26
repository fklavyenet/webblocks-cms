@php
    $assetUrl = $block->asset?->url();
    $rawUrl = trim((string) ($block->url ?? ''));
    $parsedScheme = strtolower((string) parse_url($rawUrl, PHP_URL_SCHEME));
    $safeUrl = $rawUrl !== '' && in_array($parsedScheme, ['http', 'https'], true) ? $rawUrl : null;
    $audioSource = $assetUrl ?: $safeUrl;
@endphp

@if ($audioSource || $block->title || $block->content)
    <div class="wb-card wb-card-muted">
        <div class="wb-card-body wb-stack wb-gap-3">
            @if ($block->title || $block->content)
                <div class="wb-stack wb-gap-1">
                    @if ($block->title)
                        <strong>{{ $block->title }}</strong>
                    @endif
                    @if ($block->content)
                        <p class="wb-m-0">{{ $block->content }}</p>
                    @endif
                </div>
            @endif

            @if ($audioSource)
                <audio controls preload="metadata">
                    <source src="{{ $audioSource }}">
                </audio>
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
