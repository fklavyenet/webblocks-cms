@php
    $level = in_array($block->variant, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true) ? $block->variant : 'h2';
    $text = (string) ($block->title ?? '');
    $class = $block->headerAlignmentClass();
    $classAttribute = $class ? ' class="'.$class.'"' : '';
@endphp

<{{ $level }}{!! $classAttribute !!}>{{ $text }}</{{ $level }}>
