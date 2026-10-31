@php
    $level = $block->headerLevel();
    $text = (string) $block->boundPublicValue('title', $block->title ?? '');
    $class = $block->headerAlignmentClass();
    $classAttribute = $class ? ' class="'.$class.'"' : '';
    $anchor = $block->headerAnchor() ?? '';
    $anchorAttribute = $anchor !== '' ? ' id="'.e($anchor).'"' : '';
@endphp

<{{ $level }}{!! $classAttribute !!}{!! $anchorAttribute !!} data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}">{{ $text }}</{{ $level }}>
