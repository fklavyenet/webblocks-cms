@extends('webblocks-cms::layouts.public')

@section('content')
@unless ($systemPageHasContent ?? false)
{!! app(\WebBlocks\Cms\Support\SystemPages\SystemPageRenderer::class)->defaultContent($page, $publicLocaleCode) !!}
@endunless
@endsection
