@php
    $a = fn ($key) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)->get('dynamic-authoring.'.$key, $locale);
@endphp
@extends('webblocks-cms::layouts.public', ['title' => $a('preview'), 'publicLocaleCode' => $locale])
@section('content')
<main class="wb-container wb-py-6">
    <div class="wb-alert {{ $status === 'available' ? 'wb-alert-info' : 'wb-alert-warning' }}"><strong>{{ $a('preview') }} · {{ strtoupper($locale) }}</strong><p>{{ $source->labelText() }} · {{ $a('status_'.$status) }} @if ($total !== null) ({{ $total }}) @endif</p><p>{{ $a('preview_private') }}</p></div>
    @if (in_array($status, ['available', 'empty'], true))
        @include($block->publicRenderView(), ['block' => $block])
    @endif
</main>
@endsection
