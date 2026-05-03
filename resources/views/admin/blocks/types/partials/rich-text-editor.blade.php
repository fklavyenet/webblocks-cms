@php
    $translationNotice = $translationNotice ?? null;
    $inputName = $inputName ?? 'content';
    $inputId = $inputId ?? 'content';
    $surfaceId = $surfaceId ?? $inputId.'_surface';
    $value = app(\App\Support\Blocks\RichTextHtmlSanitizer::class)->sanitize((string) ($value ?? '')) ?? '';
@endphp

<div class="wb-stack wb-gap-3" data-wb-rich-text-editor>
    @if ($translationNotice)
        <div class="wb-alert wb-alert-info">
            <div>{{ $translationNotice }}</div>
        </div>
    @endif

    <div class="wb-stack wb-gap-1">
        <label for="{{ $surfaceId }}">Rich Text</label>
        <div class="wb-cluster wb-cluster-2 wb-flex-wrap" aria-label="Rich text toolbar">
            <button type="button" class="wb-btn wb-btn-secondary wb-btn-sm" data-wb-rich-text-command="bold" aria-label="Bold"><strong>B</strong></button>
            <button type="button" class="wb-btn wb-btn-secondary wb-btn-sm" data-wb-rich-text-command="italic" aria-label="Italic"><em>I</em></button>
            <button type="button" class="wb-btn wb-btn-secondary wb-btn-sm" data-wb-rich-text-command="code" aria-label="Inline code"><code>Code</code></button>
            <button type="button" class="wb-btn wb-btn-secondary wb-btn-sm" data-wb-rich-text-command="unordered-list">Bullets</button>
            <button type="button" class="wb-btn wb-btn-secondary wb-btn-sm" data-wb-rich-text-command="ordered-list">Numbers</button>
            <button type="button" class="wb-btn wb-btn-secondary wb-btn-sm" data-wb-rich-text-command="blockquote">Quote</button>
            <button type="button" class="wb-btn wb-btn-secondary wb-btn-sm" data-wb-rich-text-command="link">Link</button>
            <button type="button" class="wb-btn wb-btn-secondary wb-btn-sm" data-wb-rich-text-command="clear">Clear</button>
        </div>
        <div class="wb-card wb-card-muted">
            <div
                id="{{ $surfaceId }}"
                class="wb-card-body"
                contenteditable="true"
                role="textbox"
                aria-multiline="true"
                data-wb-rich-text-surface
            >{!! $value !!}</div>
        </div>
        <textarea id="{{ $inputId }}" name="{{ $inputName }}" class="wb-textarea" rows="8" hidden data-wb-rich-text-input>{{ $value }}</textarea>
        <div class="wb-text-sm wb-text-muted">Safe inline formatting only. Use dedicated blocks for headings, buttons, media, and layouts.</div>
    </div>
</div>
