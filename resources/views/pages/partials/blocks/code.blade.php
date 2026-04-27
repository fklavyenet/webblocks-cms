@php
    $settings = is_array($block->settings)
        ? $block->settings
        : (json_decode((string) $block->settings, true) ?: []);
    $language = trim((string) ($settings['language'] ?? $settings['lang'] ?? ''));
    $headerMeta = trim((string) ($block->subtitle ?: $language));
@endphp

<div class="wb-card wb-card-muted">
    <div class="wb-card-body wb-stack wb-gap-2">
        @if ($block->title || $headerMeta !== '')
            <div class="wb-stack wb-gap-1">
                @if ($block->title)
                    <strong>{{ $block->title }}</strong>
                @endif

                @if ($headerMeta !== '')
                    <div class="wb-text-sm wb-text-muted">{{ $headerMeta }}</div>
                @endif
            </div>
        @endif

        <pre>@if ($language !== '')<code data-language="{{ $language }}">@else<code>@endif{{ $block->content }}</code></pre>
    </div>
</div>

@if ($block->children->isNotEmpty())
    <div class="wb-stack wb-gap-4">
        @foreach ($block->children as $child)
            @include('pages.partials.block', ['block' => $child])
        @endforeach
    </div>
@endif
