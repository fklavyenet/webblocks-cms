@if (session('theme_status'))
    <div class="wb-alert wb-alert-success wb-mb-3" role="status">{{ $text(session('theme_status')) }}</div>
@endif
@php($themeErrors = collect($errors->getMessages())->except($inlineErrors ?? [])->flatten())
@if ($themeErrors->isNotEmpty())
    <div class="wb-alert wb-alert-danger wb-mb-3" role="alert">
        <ul>
            @foreach ($themeErrors as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
