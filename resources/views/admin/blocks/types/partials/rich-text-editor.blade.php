@php
    $translationNotice = $translationNotice ?? null;
    $inputName = $inputName ?? 'content';
    $inputId = $inputId ?? 'content';
    $value = old($inputName, $value ?? '');
    $surfaceId = $inputId.'__surface';
@endphp

<div class="wb-stack wb-gap-3">
    @if ($translationNotice)
        <div class="wb-alert wb-alert-info">
            <div>{{ $translationNotice }}</div>
        </div>
    @endif

    <div class="wb-stack wb-gap-1">
        <label for="{{ $surfaceId }}">Rich Text</label>

        <div class="wb-admin-rich-text-editor" data-wb-rich-text-editor>
            <div class="wb-toolbar wb-toolbar-sm wb-admin-rich-text-toolbar" role="toolbar" aria-label="Rich Text formatting">
                <div class="wb-toolbar-start">
                    <div class="wb-action-group" role="group" aria-label="Inline formatting">
                        <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="bold" aria-label="Bold" title="Bold">B</button>
                        <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="italic" aria-label="Italic" title="Italic">I</button>
                        <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="code" aria-label="Code" title="Code">Code</button>
                    </div>

                    <span class="wb-toolbar-divider" aria-hidden="true"></span>

                    <div class="wb-action-group" role="group" aria-label="Links">
                        <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="link" aria-label="Link" title="Link">Link</button>
                    </div>

                    <span class="wb-toolbar-divider" aria-hidden="true"></span>

                    <div class="wb-action-group" role="group" aria-label="Lists">
                        <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="bullet-list" aria-label="Bullet List" title="Bullet List">• List</button>
                        <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="numbered-list" aria-label="Numbered List" title="Numbered List">1. List</button>
                    </div>

                    <span class="wb-toolbar-divider" aria-hidden="true"></span>

                    <div class="wb-action-group" role="group" aria-label="Cleanup">
                        <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="clear" aria-label="Clear formatting" title="Clear formatting">Clear</button>
                    </div>
                </div>
            </div>

            <div
                id="{{ $surfaceId }}"
                class="wb-admin-rich-text-surface"
                contenteditable="true"
                role="textbox"
                aria-label="Rich Text"
                aria-multiline="true"
                data-wb-rich-text-surface
            ></div>

            <textarea
                id="{{ $inputId }}"
                name="{{ $inputName }}"
                class="wb-textarea wb-admin-rich-text-input"
                rows="8"
                autocomplete="off"
                data-wb-rich-text-input
                hidden
            >{{ $value }}</textarea>
        </div>
        <div class="wb-text-sm wb-text-muted">Use safe formatting: paragraphs, bold, italic, inline code, links, bullet lists, and numbered lists. Headings should use Header blocks.</div>
    </div>
</div>
