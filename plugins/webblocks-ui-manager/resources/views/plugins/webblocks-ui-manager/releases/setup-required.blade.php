@extends('webblocks-cms::layouts.admin', ['title' => $title ?? 'WebBlocks UI Releases', 'heading' => 'WebBlocks UI Releases'])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'WebBlocks UI Releases',
        'description' => 'Setup Required: WebBlocks UI Manager is enabled, but its release database setup is not complete yet.',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-header">
            <strong>Setup Required: Plugin Migrations Pending</strong>
        </div>
        <div class="wb-card-body wb-stack wb-gap-3">
            <div class="wb-alert wb-alert-warning">{{ $message }}</div>
            <p>Complete setup from the plugin detail screen before using release metadata, dry-run validation, or publish workflows.</p>
        </div>
        <div class="wb-card-footer wb-cluster wb-cluster-2">
            <a href="{{ $pluginDetailUrl }}" class="wb-btn wb-btn-primary">
                <i class="wb-icon wb-icon-settings" aria-hidden="true"></i>
                Open Plugin Setup
            </a>
            @if (! empty($pluginSetupUrl) && auth()->user()?->isSuperAdmin())
                <form method="POST" action="{{ $pluginSetupUrl }}">
                    @csrf
                    <button type="submit" class="wb-btn wb-btn-secondary">
                        <i class="wb-icon wb-icon-play" aria-hidden="true"></i>
                        Run Plugin Migrations
                    </button>
                </form>
            @endif
        </div>
    </div>
@endsection
