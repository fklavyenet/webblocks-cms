<dialog class="wb-modal" id="{{ $id }}" aria-labelledby="{{ $id }}-title">
    <div class="wb-modal-dialog">
        <div class="wb-modal-header">
            <h2 class="wb-modal-title" id="{{ $id }}-title" @isset($language) lang="{{ $language }}" @endisset>{{ $name }}</h2>
            <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ __('webblocks-cms::admin.common.close') }}">
                <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
            </button>
        </div>
        <div class="wb-modal-body">
            <div class="wb-table-wrap">
                <table class="wb-table">
                    <tbody>
                        @foreach ($details as $label => $value)
                            <tr><th scope="row" class="wb-table-key">{{ $label }}</th><td class="wb-table-break">{{ $value }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="wb-modal-footer">
            <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ __('webblocks-cms::admin.common.close') }}</button>
        </div>
    </div>
</dialog>
