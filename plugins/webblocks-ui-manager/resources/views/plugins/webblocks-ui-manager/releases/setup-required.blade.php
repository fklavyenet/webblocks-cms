@extends('webblocks-cms::layouts.admin', ['title' => 'WebBlocks UI Manager Setup Required', 'heading' => 'WebBlocks UI Manager'])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'Setup Required',
        'description' => 'WebBlocks UI Manager is enabled, but its plugin-owned database setup is not complete yet.',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-header">
            <strong>Plugin Migrations Pending</strong>
        </div>
        <div class="wb-card-body wb-stack wb-gap-3">
            <div class="wb-alert wb-alert-warning">{{ $message }}</div>
            <p>Complete setup from the plugin detail screen before using release metadata, dry-run validation, or publish workflows.</p>
        </div>
        <div class="wb-card-footer">
            <a href="{{ $pluginDetailUrl }}" class="wb-btn wb-btn-primary">
                <i class="wb-icon wb-icon-settings" aria-hidden="true"></i>
                Open Plugin Setup
            </a>
        </div>
    </div>
@endsection
