@if ($status = session('panel_language_status'))
    <div class="wb-alert wb-alert-success" role="status">{{ $text($status) }}</div>
@endif

@if ($errors->any())
    <div class="wb-alert wb-alert-danger" role="alert">{{ $errors->first() }}</div>
@endif
