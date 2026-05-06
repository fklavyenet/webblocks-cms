@php
    $level = in_array($block->variant, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true) ? $block->variant : 'h2';
    $text = (string) ($block->title ?? '');
    $class = $block->headerAlignmentClass();
    $classAttribute = $class ? ' class="'.$class.'"' : '';
    $anchor = $block->headerAnchor() ?? '';
    $anchorAttribute = $anchor !== '' ? ' id="'.e($anchor).'"' : '';
@endphp

<{{ $level }}{!! $classAttribute !!}{!! $anchorAttribute !!} data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">{{ $text }}</{{ $level }}>
