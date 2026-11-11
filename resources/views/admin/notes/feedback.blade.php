@if (session('note_status'))
    <div class="wb-alert wb-alert-success" role="status">{{ $text(session('note_status')) }}</div>
@endif
