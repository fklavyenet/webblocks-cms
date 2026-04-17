@php
    $columnBody = '<div class="wb-stack wb-gap-2">';

    if ($block->title) {
        $columnBody .= '<strong>'.e($block->title).'</strong>';
    }

    if ($block->content) {
        $columnBody .= '<p>'.e($block->content).'</p>';
    }

    $columnBody .= '</div>';
@endphp

@if ($block->url)
    <a href="{{ $block->url }}" class="wb-no-decoration">{!! $columnBody !!}</a>
@else
    {!! $columnBody !!}
@endif
