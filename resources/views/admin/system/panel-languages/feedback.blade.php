@if ($status = session('panel_language_status'))
    <div class="wb-alert wb-alert-success" role="status">{{ $text($status) }}</div>
@endif

@php($languageErrors = collect($errors->getMessages())->except($inlineErrors ?? [])->flatten())
@if ($languageErrors->isNotEmpty())
    <div class="wb-alert wb-alert-danger" role="alert">{{ $languageErrors->first() }}</div>
@endif
