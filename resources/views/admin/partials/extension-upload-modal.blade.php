<dialog class="wb-modal" id="{{ $id }}" aria-labelledby="{{ $id }}-title" @if ($error) data-wb-admin-autoload-overlay @endif>
    <form class="wb-modal-dialog" method="POST" action="{{ $action }}" enctype="multipart/form-data">
        @csrf
        @foreach ($hidden ?? [] as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <div class="wb-modal-header">
            <h2 id="{{ $id }}-title" class="wb-modal-title">{{ $title }}</h2>
            <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ __('webblocks-cms::admin.common.close') }}">
                <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
            </button>
        </div>
        <div class="wb-modal-body">
            <div class="wb-field">
                <label class="wb-label" for="{{ $fieldId }}">{{ $fileLabel }}</label>
                <input id="{{ $fieldId }}" name="{{ $field }}" type="file" class="wb-file" accept=".zip,application/zip"
                    aria-describedby="{{ $fieldId }}-help{{ $error ? ' '.$fieldId.'-error' : '' }}"
                    @if ($error) aria-invalid="true" @endif required>
                <p class="wb-text-sm wb-text-muted wb-m-0" id="{{ $fieldId }}-help">{{ $help }}</p>
                @if ($error)
                    <p class="wb-alert wb-alert-danger" id="{{ $fieldId }}-error" role="alert">{{ $error }}</p>
                @endif
            </div>
        </div>
        <div class="wb-modal-footer">
            <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ __('webblocks-cms::admin.common.cancel') }}</button>
            <button type="submit" class="wb-btn wb-btn-primary" data-wb-busy data-wb-busy-label="{{ __('webblocks-cms::modal-submit.uploading') }}">
                <span data-wb-busy-text>{{ __('webblocks-cms::admin.extensions_ui.upload') }}</span>
            </button>
        </div>
    </form>
</dialog>
