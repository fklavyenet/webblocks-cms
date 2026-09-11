@php
    $class = $block->plainTextAlignmentClass();
    $classAttribute = $class ? ' class="'.$class.'"' : '';
@endphp

<p{!! $classAttribute !!}>{{ (string) $block->boundPublicValue('content', $block->content ?? '') }}</p>
