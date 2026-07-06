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
                        <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="bold" aria-label="{{ $adminText('bold') }}" title="{{ $adminText('bold') }}">B</button>
                        <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="italic" aria-label="{{ $adminText('italic') }}" title="{{ $adminText('italic') }}">I</button>
                        <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="code" aria-label="{{ $adminText('code') }}" title="{{ $adminText('code') }}">{{ $adminText('code') }}</button>
                    </div>

                    <span class="wb-toolbar-divider" aria-hidden="true"></span>

                    <div class="wb-action-group" role="group" aria-label="{{ $adminText('links') }}">
                        <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="link" aria-label="{{ $adminText('link') }}" title="{{ $adminText('link') }}">{{ $adminText('link') }}</button>
                    </div>

                    <span class="wb-toolbar-divider" aria-hidden="true"></span>

                    <div class="wb-action-group" role="group" aria-label="{{ $adminText('lists') }}">
                        <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="bullet-list" aria-label="{{ $adminText('bullet_list') }}" title="{{ $adminText('bullet_list') }}">{{ $adminText('bullet_list_button') }}</button>
                        <button type="button" class="wb-btn wb-btn-sm wb-btn-ghost" data-wb-rich-text-action="numbered-list" aria-label="{{ $adminText('numbered_list') }}" title="{{ $adminText('numbered_list') }}">{{ $adminText('numbered_list_button') }}</button>
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
    </div>
</div>
