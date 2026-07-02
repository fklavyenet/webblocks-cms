@extends('webblocks-cms::layouts.admin', ['title' => $title, 'heading' => $title])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'Commerce Orders',
        'description' => 'Run plugin setup before reviewing commerce orders.',
    ])

    <div class="wb-card">
        <div class="wb-card-body wb-stack wb-gap-3">
            <div class="wb-alert wb-alert-warning">
                <div>
                    <div class="wb-alert-title">Plugin Setup Required</div>
                    <div>{{ $message }}</div>
                </div>
            </div>

            <div class="wb-cluster wb-cluster-2">
                <a href="{{ $pluginDetailUrl }}" class="wb-btn wb-btn-secondary">Plugin Detail</a>
                @if (auth()->user()?->isSuperAdmin())
                    <form method="POST" action="{{ $pluginSetupUrl }}">
                        @csrf
                        <button type="submit" class="wb-btn wb-btn-primary">Run Plugin Migrations</button>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endsection
