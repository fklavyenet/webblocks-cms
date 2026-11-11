@if (session('theme_status'))
    <div class="wb-alert wb-alert-success wb-mb-3" role="status">{{ $text(session('theme_status')) }}</div>
@endif
@if ($errors->any())
    <div class="wb-alert wb-alert-danger wb-mb-3" role="alert">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
