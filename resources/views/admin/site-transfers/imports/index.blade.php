@extends('layouts.admin', ['title' => 'Export / Import', 'heading' => 'Export / Import'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Export / Import',
        'description' => 'Site transfer history now lives on one combined operational screen.',
        'actions' => '<a href="'.route('admin.site-transfers.exports.index').'" class="wb-btn wb-btn-primary">Open Export / Import</a>',
    ])

    @include('admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-body wb-stack wb-gap-2">
            <div>Exports and imports now render together on the main `Export / Import` screen.</div>
            <div>Use the combined screen for export history, import history, and the primary `Run Export` action.</div>
        </div>
    </div>
@endsection
