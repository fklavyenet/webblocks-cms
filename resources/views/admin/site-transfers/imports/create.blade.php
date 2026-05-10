@extends('layouts.admin', ['title' => 'Run Import', 'heading' => 'Run Import'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Run Import',
        'description' => 'Upload a validated site export package, preview package metadata, then create a new local site from that package.',
        'actions' => '<a href="'.route('admin.site-transfers.exports.index').'" class="wb-btn wb-btn-secondary">Back to Export / Import</a>',
    ])

    @include('admin.partials.flash')

    @if ($errors->has('site_import'))
        <div class="wb-alert wb-alert-danger">{{ $errors->first('site_import') }}</div>
    @endif

    <div class="wb-card">
        <form method="POST" action="{{ route('admin.site-transfers.imports.inspect') }}" enctype="multipart/form-data" class="wb-stack wb-gap-0">
            @csrf

            <div class="wb-card-body">
                <div class="wb-stack wb-gap-2">
                    <label for="archive">Import package (.zip)</label>
                    <input id="archive" type="file" name="archive" class="wb-input" accept=".zip,application/zip" required>
                    <div class="wb-text-sm wb-text-muted">V1 imports create a new local site from the package. Overwrite and merge flows are intentionally out of scope for now.</div>
                </div>
            </div>

            <div class="wb-card-footer">
                <x-admin.form-actions :cancel-url="route('admin.site-transfers.exports.index')" submit-label="Validate Package" />
            </div>
        </form>
    </div>
@endsection
