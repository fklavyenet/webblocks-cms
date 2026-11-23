<section class="wb-card" aria-labelledby="{{ $id }}-title">
    <div class="wb-card-header">
        <h2 id="{{ $id }}-title" class="wb-card-title">{{ $title }}</h2>
    </div>
    <form method="POST" action="{{ $action }}" enctype="multipart/form-data">
        @csrf
        @foreach ($hidden ?? [] as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <div class="wb-card-body">
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
        <div class="wb-card-footer wb-cluster wb-cluster-end wb-cluster-2 wb-flex-wrap">
            <button type="submit" class="wb-btn wb-btn-primary" data-wb-busy data-wb-busy-label="{{ __('webblocks-cms::modal-submit.uploading') }}">
                <span data-wb-busy-text>{{ __('webblocks-cms::admin.extensions_ui.upload') }}</span>
            </button>
        </div>
    </form>
</section>
