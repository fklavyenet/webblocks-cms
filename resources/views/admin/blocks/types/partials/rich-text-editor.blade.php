@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key, array $replace = []) => $adminTranslator->get('admin.blocks.partials.rich_text_editor.'.$key, $adminLocale, $replace);
    $translationNotice = $translationNotice ?? null;
    $inputName = $inputName ?? 'content';
    $inputId = $inputId ?? 'content';
    $value = old($inputName, $value ?? '');
    $surfaceId = $inputId.'__surface';
@endphp

@once
    @push('admin-scripts')
        @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/rich-text-editor.js'])
    @endpush

    @push('overlays')
        @include('webblocks-cms::admin.blocks.types.partials.rich-text-link-modal')
    @endpush
@endonce

<div class="wb-stack wb-gap-3">
    @if ($translationNotice)
        <div class="wb-alert wb-alert-info">
            <div>{{ $translationNotice }}</div>
        </div>
    @endif

    <div class="wb-stack wb-gap-1">
        <label for="{{ $surfaceId }}">{{ $adminText('rich_text') }}</label>

        <div class="wb-admin-rich-text-editor" data-wb-rich-text-editor>
            <div class="wb-toolbar wb-toolbar-sm wb-admin-rich-text-toolbar" role="toolbar" aria-label="{{ $adminText('formatting') }}">
                <div class="wb-toolbar-start">
                    <div class="wb-action-group" role="group" aria-label="{{ $adminText('inline_formatting') }}">
                        <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="bold" aria-pressed="false" aria-label="{{ $adminText('bold') }}" title="{{ $adminText('bold_title') }}">B</button>
                        <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="italic" aria-pressed="false" aria-label="{{ $adminText('italic') }}" title="{{ $adminText('italic_title') }}">I</button>
                        <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="strikethrough" aria-pressed="false" aria-label="{{ $adminText('strikethrough') }}" title="{{ $adminText('strikethrough') }}"><s>S</s></button>
                        <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="code" aria-pressed="false" aria-label="{{ $adminText('code') }}" title="{{ $adminText('code') }}">{{ $adminText('code') }}</button>
                    </div>

                    <span class="wb-toolbar-divider" aria-hidden="true"></span>

                    <div class="wb-action-group" role="group" aria-label="{{ $adminText('links') }}">
                        <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="link" aria-pressed="false" aria-label="{{ $adminText('link') }}" title="{{ $adminText('link_title_shortcut') }}">{{ $adminText('link') }}</button>
                    </div>

                    <span class="wb-toolbar-divider" aria-hidden="true"></span>

                    <div class="wb-action-group" role="group" aria-label="{{ $adminText('lists') }}">
                        <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="bullet-list" aria-pressed="false" aria-label="{{ $adminText('bullet_list') }}" title="{{ $adminText('bullet_list') }}">{{ $adminText('bullet_list_button') }}</button>
                        <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="numbered-list" aria-pressed="false" aria-label="{{ $adminText('numbered_list') }}" title="{{ $adminText('numbered_list') }}">{{ $adminText('numbered_list_button') }}</button>
                        <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="outdent" aria-label="{{ $adminText('outdent') }}" title="{{ $adminText('outdent') }}"><i class="wb-icon wb-icon-chevron-left" aria-hidden="true"></i></button>
                        <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="indent" aria-label="{{ $adminText('indent') }}" title="{{ $adminText('indent') }}"><i class="wb-icon wb-icon-chevron-right" aria-hidden="true"></i></button>
                    </div>

                    <span class="wb-toolbar-divider" aria-hidden="true"></span>

                    <div class="wb-action-group" role="group" aria-label="{{ $adminText('blocks') }}">
                        <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="quote" aria-pressed="false" aria-label="{{ $adminText('quote') }}" title="{{ $adminText('quote') }}">{{ $adminText('quote_button') }}</button>
                    </div>

                    <span class="wb-toolbar-divider" aria-hidden="true"></span>

                    <div class="wb-action-group" role="group" aria-label="{{ $adminText('cleanup') }}">
                        <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="clear" aria-label="{{ $adminText('clear_formatting') }}" title="{{ $adminText('clear_formatting') }}">{{ $adminText('clear') }}</button>
                    </div>
                </div>
            </div>

            <div
                id="{{ $surfaceId }}"
                class="wb-admin-rich-text-surface"
                contenteditable="true"
                role="textbox"
                aria-label="{{ $adminText('rich_text') }}"
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
        <div class="wb-text-sm wb-text-muted">{{ $adminText('help') }}</div>
        <div class="wb-text-sm wb-text-muted">{{ $adminText('shortcuts_help') }}</div>
    </div>
</div>
