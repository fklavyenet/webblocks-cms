@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $adminLocale = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin($key, $adminLocale, $replace);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $adminText('site_transfers.run_import'), 'heading' => $adminText('site_transfers.run_import')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $adminText('site_transfers.run_import'),
        'description' => $adminText('site_transfers.run_import_description'),
        'actions' => '<a href="'.route('admin.site-transfers.exports.index').'" class="wb-btn wb-btn-secondary">'.$adminText('site_transfers.back_to_export_import').'</a>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    @if ($errors->has('site_import'))
        <div class="wb-alert wb-alert-danger">{{ $errors->first('site_import') }}</div>
    @endif

    <div class="wb-card">
        <div class="wb-card-body">
            <form method="POST" action="{{ route('admin.site-transfers.imports.inspect') }}" enctype="multipart/form-data" class="wb-stack wb-gap-4">
                @csrf

                <div class="wb-stack wb-gap-2">
                    <label for="archive">{{ $adminText('site_transfers.import_package_zip') }}</label>
                    <input id="archive" type="file" name="archive" class="wb-input" accept=".zip,application/zip" required>
                    <div class="wb-text-sm wb-text-muted">{{ $adminText('site_transfers.import_package_help') }}</div>
                </div>

                <div class="wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                    <div class="wb-flex wb-items-center wb-gap-2 wb-flex-wrap">
                        <a href="{{ route('admin.site-transfers.exports.index') }}" class="wb-btn wb-btn-secondary">{{ $adminText('common.cancel') }}</a>
                        <button type="submit" class="wb-btn wb-btn-primary">{{ $adminText('site_transfers.validate_package') }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
@endsection
