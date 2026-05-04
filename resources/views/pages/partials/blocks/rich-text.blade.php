@php
    $content = trim((string) ($block->content ?? ''));
    $renderer = app(\App\Support\Formatting\InlineRichTextRenderer::class);
@endphp

@if ($content !== '')
    <div class="wb-stack wb-gap-2">{!! nl2br($renderer->render($content)) !!}</div>
@endif
