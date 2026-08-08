@php
    $linkModalLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $linkModalTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $linkModalText = fn (string $key) => $linkModalTranslator->get('admin.blocks.partials.rich_text_editor.'.$key, $linkModalLocale);
@endphp

{{-- One dialog serves every rich text field on the page: the editor that opened
     it owns the pending selection, and the dialog writes back to that one. --}}
<div
    class="wb-modal"
    id="wb_rich_text_link_modal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="wb_rich_text_link_title"
    data-wb-rich-text-link-modal
    data-invalid-url-message="{{ $linkModalText('link_invalid') }}"
    hidden
>
    <div class="wb-modal-dialog">
        <div class="wb-modal-header">
            <h2 class="wb-modal-title" id="wb_rich_text_link_title">{{ $linkModalText('link_title') }}</h2>

            <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $linkModalText('link_cancel') }}">
                <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
            </button>
        </div>

        <div class="wb-modal-body wb-stack wb-gap-3">
            <div class="wb-stack wb-gap-1">
                <label for="wb_rich_text_link_url">{{ $linkModalText('link_url') }}</label>
                <input type="text" id="wb_rich_text_link_url" class="wb-input" autocomplete="off" spellcheck="false" placeholder="https://" data-wb-rich-text-link-url>
                <div class="wb-text-sm wb-text-muted">{{ $linkModalText('link_url_help') }}</div>
            </div>

            <div class="wb-stack wb-gap-1">
                <label for="wb_rich_text_link_text">{{ $linkModalText('link_text') }}</label>
                <input type="text" id="wb_rich_text_link_text" class="wb-input" autocomplete="off" data-wb-rich-text-link-text>
            </div>

            <div class="wb-alert wb-alert-danger" data-wb-rich-text-link-error hidden></div>
        </div>

        <div class="wb-modal-footer wb-flex wb-justify-between wb-gap-2">
            <button type="button" class="wb-btn wb-btn-secondary" data-wb-rich-text-link-remove hidden>{{ $linkModalText('link_remove') }}</button>

            <div class="wb-cluster wb-cluster-2">
                <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ $linkModalText('link_cancel') }}</button>
                <button type="button" class="wb-btn wb-btn-primary" data-wb-rich-text-link-apply>{{ $linkModalText('link_apply') }}</button>
            </div>
        </div>
    </div>
</div>
