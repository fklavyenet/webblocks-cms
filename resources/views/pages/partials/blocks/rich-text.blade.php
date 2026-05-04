@php
    $content = trim((string) ($block->content ?? ''));
    $renderer = app(\App\Support\Formatting\SafeRichTextRenderer::class);
    $rendered = $renderer->render($content)->toHtml();
@endphp

@if ($rendered !== '')
    <div class="wb-rich-text wb-rich-text-readable">{!! $rendered !!}</div>
@endif
