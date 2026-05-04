@php
    $content = trim((string) ($block->content ?? ''));
    $renderer = app(\App\Support\Formatting\SafeRichTextRenderer::class);
@endphp

@if ($content !== '')
    <div class="wb-stack wb-gap-3">{!! $renderer->render($content) !!}</div>
@endif
