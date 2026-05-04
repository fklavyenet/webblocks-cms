@php
    $translationNotice = $translationNotice ?? null;
    $inputName = $inputName ?? 'content';
    $inputId = $inputId ?? 'content';
    $value = old($inputName, $value ?? '');
@endphp

<div class="wb-stack wb-gap-3">
    @if ($translationNotice)
        <div class="wb-alert wb-alert-info">
            <div>{{ $translationNotice }}</div>
        </div>
    @endif

    <div class="wb-stack wb-gap-1">
        <label for="{{ $inputId }}">Rich Text</label>

        <div class="wb-cluster wb-cluster-2" role="toolbar" aria-label="Rich Text formatting">
            <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="bold">Bold</button>
            <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="italic">Italic</button>
            <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="code">Code</button>
            <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="link">Link</button>
            <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="bullet-list">Bullet List</button>
            <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="numbered-list">Numbered List</button>
        </div>

        <textarea id="{{ $inputId }}" name="{{ $inputName }}" class="wb-textarea" rows="8" placeholder="Write body copy. Use **bold**, *italic*, `code`, [links](https://example.com), and lists." autocomplete="off" data-wb-rich-text-editor>{{ $value }}</textarea>
        <div class="wb-text-sm wb-text-muted">Use safe formatting: bold, italic, inline code, links, bullet lists, and numbered lists. Headings should use Header blocks.</div>
    </div>
</div>
