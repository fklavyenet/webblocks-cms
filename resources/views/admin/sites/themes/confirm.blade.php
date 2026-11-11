<dialog class="wb-modal" id="{{ $id }}" aria-labelledby="{{ $id }}-title">
    <form class="wb-modal-dialog" method="post" action="{{ route('admin.sites.themes.'.$operation, $site) }}">
        @csrf
        @if ($operation === 'activate')
            <input type="hidden" name="confirmation" value="{{ $confirmation }}">
        @else
            <input type="hidden" name="expected" value="{{ $current?->id ?? 0 }}">
            <input type="hidden" name="revision" value="{{ $revision }}">
        @endif
        <div class="wb-modal-header">
            <h2 class="wb-modal-title" id="{{ $id }}-title">{{ $text($operation) }}</h2>
        </div>
        <div class="wb-modal-body">
            <p>{{ $description }}</p>
            <p><strong>{{ $site->name }}</strong> · {{ $label($current) }} → {{ $target }}</p>
        </div>
        <div class="wb-modal-footer">
            <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ $text('cancel') }}</button>
            <button type="submit" class="wb-btn wb-btn-primary">{{ $text($operation) }}</button>
        </div>
    </form>
</dialog>
