@php
    $content = trim((string) $block->content);

    if ($content === '') {
        return;
    }

    $settings = is_array($block->settings)
        ? $block->settings
        : (json_decode((string) $block->settings, true) ?: []);

    $language = trim((string) ($settings['language'] ?? $settings['lang'] ?? ''));
    $language = strtolower($language);
    $language = preg_replace('/[^a-z0-9#+-]+/', '-', $language) ?? '';
    $language = trim($language, '-');
@endphp

<pre>@if ($language !== '')<code data-language="{{ $language }}">@else<code>@endif{{ $block->content }}</code></pre>

@if ($block->children->isNotEmpty())
    <div class="wb-stack wb-gap-4">
        @foreach ($block->children as $child)
            @include('pages.partials.block', ['block' => $child])
        @endforeach
    </div>
@endif
