@if (session('status'))
    <div class="wb-alert wb-alert-success">
        <div>{{ session('status') }}</div>
    </div>
@endif

@if ($errors->any())
    <div class="wb-alert wb-alert-danger">
        <div>{{ $errors->first() }}</div>
    </div>
@endif
