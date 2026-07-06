@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $adminLocale = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin($key, $adminLocale, $replace);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $adminText('site_transfers.title'), 'heading' => $adminText('site_transfers.title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $adminText('site_transfers.title'),
        'description' => $adminText('site_transfers.combined_description'),
        'actions' => '<a href="'.route('admin.site-transfers.exports.index').'" class="wb-btn wb-btn-primary">'.$adminText('site_transfers.open_export_import').'</a>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-body wb-stack wb-gap-2">
            <div>{{ $adminText('site_transfers.combined_notice') }}</div>
            <div>{{ $adminText('site_transfers.combined_help') }}</div>
        </div>
    </div>
@endsection
