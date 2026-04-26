@php
    $asset = $block->asset;
    $assetUrl = $asset?->url();
    $rawUrl = trim((string) ($block->url ?? ''));
    $parsedScheme = strtolower((string) parse_url($rawUrl, PHP_URL_SCHEME));
    $safeUrl = $rawUrl !== '' && in_array($parsedScheme, ['http', 'https', 'mailto'], true) ? $rawUrl : null;
    $downloadUrl = $assetUrl ?: $safeUrl;
@endphp

@if ($downloadUrl || $block->title || $block->content)
    <div class="wb-card wb-card-muted">
        <div class="wb-card-body wb-stack wb-gap-2">
            @if ($block->title)
                <strong>{{ $block->title }}</strong>
            @endif

            @if ($block->content)
                <p class="wb-m-0">{{ $block->content }}</p>
            @endif

            @if ($downloadUrl)
                <a href="{{ $downloadUrl }}" class="wb-btn wb-btn-secondary" @if ($assetUrl) download @endif>
                    {{ $assetUrl ? 'Download' : 'Open file' }}
                </a>
            @endif

            @if ($asset)
                <span class="wb-text-sm wb-text-muted">{{ $asset->filename }}{{ $asset->mime_type ? ' | '.$asset->mime_type : '' }}</span>
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
