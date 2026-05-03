@php($content = trim((string) ($block->content ?? '')))

@if ($content !== '')
    <div class="wb-stack wb-gap-2">{!! $content !!}</div>
@endif
