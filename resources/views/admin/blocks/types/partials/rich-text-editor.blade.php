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
        <textarea id="{{ $inputId }}" name="{{ $inputName }}" class="wb-textarea" rows="8">{{ $value }}</textarea>
        <div class="wb-text-sm wb-text-muted">Safe inline formatting only. Use backticks for inline code, for example <code>`light`</code>, <code>`dark`</code>, or <code>`auto`</code>. Use dedicated blocks for headings, buttons, media, and layouts.</div>
    </div>
</div>
