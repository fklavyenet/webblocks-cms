@php
    $content = trim((string) ($block->content ?? ''));
    $renderer = app(\App\Support\Formatting\SafeRichTextRenderer::class);
@endphp

@if ($content !== '')
    <div class="wb-rich-text wb-rich-text-readable">{!! $renderer->render($content) !!}</div>
@endif
