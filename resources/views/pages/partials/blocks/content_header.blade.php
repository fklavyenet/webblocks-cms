@php
    $title = trim((string) ($block->title ?? ''));
    $introText = trim((string) ($block->subtitle ?? ''));
    $headingTag = in_array($block->variant, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true) ? $block->variant : 'h1';
    $metaItems = $block->metaItems();
    $alignmentClass = $block->contentHeaderAlignmentClass();
    $headerClass = trim('wb-content-header '.($alignmentClass ?? ''));
@endphp

<header class="{{ $headerClass }}">
    <{{ $headingTag }} class="wb-content-title">{{ $title }}</{{ $headingTag }}>

    @if ($introText !== '')
        <p class="wb-content-subtitle">{{ $introText }}</p>
    @endif

    @if ($metaItems->isNotEmpty())
        <div class="wb-content-meta">
            @foreach ($metaItems as $metaItem)
                <span>{{ $metaItem }}</span>

                @if (! $loop->last)
                    <span class="wb-content-meta-divider"></span>
                @endif
            @endforeach
        </div>
    @endif
</header>
