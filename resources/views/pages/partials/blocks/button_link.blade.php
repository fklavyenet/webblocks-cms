@php
    $buttonUrl = $block->buttonLinkUrl();
    $blankTarget = $block->buttonLinkTarget() === '_blank';
@endphp

@if ($buttonUrl)
    <a href="{{ $buttonUrl }}" class="{{ $block->buttonLinkVariantClass() }}"@if ($blankTarget) target="_blank" rel="noopener noreferrer"@endif>{{ $block->title }}</a>
@endif
