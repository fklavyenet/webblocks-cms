@php
    $assetUrl = $block->asset?->url();
    $rawUrl = trim((string) ($block->url ?? ''));
    $parsedScheme = strtolower((string) parse_url($rawUrl, PHP_URL_SCHEME));
    $safeUrl = $rawUrl !== '' && in_array($parsedScheme, ['http', 'https'], true) ? $rawUrl : null;
    $host = strtolower((string) parse_url($safeUrl ?? '', PHP_URL_HOST));
    $embedUrl = null;

    if ($safeUrl && ($host === 'youtu.be' || $host === 'www.youtube.com' || $host === 'youtube.com')) {
        $videoId = null;

        if ($host === 'youtu.be') {
            $videoId = trim((string) parse_url($safeUrl, PHP_URL_PATH), '/');
        } else {
            parse_str((string) parse_url($safeUrl, PHP_URL_QUERY), $query);
            $videoId = $query['v'] ?? null;
        }

        if (is_string($videoId) && $videoId !== '') {
            $embedUrl = 'https://www.youtube.com/embed/'.$videoId;
        }
    }

    if ($safeUrl && ($host === 'vimeo.com' || $host === 'www.vimeo.com')) {
        $videoId = trim((string) parse_url($safeUrl, PHP_URL_PATH), '/');

        if ($videoId !== '' && preg_match('/^[0-9]+$/', $videoId) === 1) {
            $embedUrl = 'https://player.vimeo.com/video/'.$videoId;
        }
    }

    $videoSource = $assetUrl ?: ($embedUrl ? null : $safeUrl);
@endphp

@if ($assetUrl || $embedUrl || $safeUrl || $block->title || $block->content)
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

            @if ($videoSource)
                <video controls preload="metadata">
                    <source src="{{ $videoSource }}">
                </video>
            @elseif ($embedUrl)
                <iframe
                    src="{{ $embedUrl }}"
                    title="{{ $block->title ?: 'Embedded video' }}"
                    loading="lazy"
                    referrerpolicy="strict-origin-when-cross-origin"
                    allowfullscreen
                ></iframe>
            @elseif ($safeUrl)
                <a href="{{ $safeUrl }}" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer">Open video</a>
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
