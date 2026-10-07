@php
    $focusLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $focusTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $focusText = fn (string $key) => $focusTranslator->get('admin.blocks.partials.rich_text_editor.'.$key, $focusLocale);
@endphp

<div class="wb-modal wb-admin-rich-text-focus-modal" id="wb_rich_text_focus_modal" role="dialog" aria-modal="true" aria-labelledby="wb_rich_text_focus_title" data-wb-rich-text-focus-modal hidden>
    <div class="wb-modal-dialog">
        <div class="wb-modal-header">
            <h2 class="wb-modal-title" id="wb_rich_text_focus_title">{{ $focusText('focus_mode') }}</h2>
            <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $focusText('close_focus') }}"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></button>
        </div>
        <div class="wb-modal-body" data-wb-rich-text-focus-body></div>
        <div class="wb-modal-footer">
            <span class="wb-text-sm wb-text-muted">{{ $focusText('focus_help') }}</span>
            <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ $focusText('close_focus') }}</button>
        </div>
    </div>
</div>
