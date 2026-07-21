@php
    $content = trim((string) ($block->content ?? ''));
    $renderer = app(\WebBlocks\Cms\Support\Formatting\SafeRichTextRenderer::class);
    $rendered = $renderer->render($content)->toHtml();
@endphp

@if ($rendered !== '')
    <div class="wb-rich-text">{!! $rendered !!}</div>
@endif
