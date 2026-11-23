<dialog class="wb-modal" id="{{ $id }}" aria-labelledby="{{ $id }}-title" aria-describedby="{{ $id }}-description">
    <form class="wb-modal-dialog" method="POST" action="{{ $action }}" @if ($operation === 'restore') data-wb-modal-submit-progress="restoring" @endif>
        @csrf
        <input type="hidden" name="operation" value="{{ $operation }}">
        @isset($expectedVersion)
            <input type="hidden" name="expected_version" value="{{ $expectedVersion }}">
        @endisset

        <div class="wb-modal-header">
            <h2 class="wb-modal-title" id="{{ $id }}-title">{{ $title }}</h2>
            <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $cancelLabel }}">
                <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
            </button>
        </div>
        <div class="wb-modal-body">
            <p class="wb-m-0" id="{{ $id }}-description">{{ $description }}</p>
        </div>
        <div class="wb-modal-footer">
            <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal" autofocus>{{ $cancelLabel }}</button>
            <button type="submit" class="wb-btn {{ $operation === 'disable' ? 'wb-btn-danger' : 'wb-btn-primary' }}">{{ $title }}</button>
        </div>
    </form>
</dialog>
