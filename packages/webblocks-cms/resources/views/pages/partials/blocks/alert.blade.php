@php
    $title = trim((string) ($block->title ?? ''));
    $content = trim((string) ($block->content ?? ''));
    $variantClass = $block->alertVariantClass();
@endphp

<div class="wb-alert {{ $variantClass }}">
    @if ($title !== '')
        <h3 class="wb-alert-title">{{ $title }}</h3>
    @endif

    @if ($content !== '')
        <p>{{ $content }}</p>
    @endif
</div>
