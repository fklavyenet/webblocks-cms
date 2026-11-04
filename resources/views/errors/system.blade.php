@extends('webblocks-cms::layouts.public')

@section('content')
@php
    $translator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $errorText = fn (string $key) => $translator->public('errors.'.$systemError.'.'.$key, $publicLocaleCode);
    $home = app(\WebBlocks\Cms\Support\Pages\PageRouteResolver::class)->homePath($publicLocaleCode, $page->site) ?? '/';
@endphp
<div class="wb-container wb-section wb-stack wb-gap-3">
    <div class="wb-error-code" aria-hidden="true">{{ $systemErrorCode }}</div>
    <h1>{{ $errorText('title') }}</h1>
    <p>{{ $errorText('body') }}</p>
    <p><a href="{{ $systemErrorCode === 503 ? '' : $home }}" class="wb-btn wb-btn-primary">{{ $errorText('cta') }}</a></p>
</div>
@endsection
