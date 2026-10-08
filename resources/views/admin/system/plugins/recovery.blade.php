@extends('webblocks-cms::layouts.guest', ['title' => __('webblocks-cms::admin.plugin_recovery.title')])
@section('content')
<div class="wb-container wb-stack wb-stack-4 wb-p-6">
    <h1>{{ __('webblocks-cms::admin.plugin_recovery.title') }}</h1>
    <p>{{ __('webblocks-cms::admin.plugin_recovery.description') }}</p>
    @if(session('status'))<div class="wb-alert wb-alert-success" role="status">{{ session('status') }}</div>@endif
    @if($errors->any())<div class="wb-alert wb-alert-danger" role="alert">{{ $errors->first() }}</div>@endif
    @forelse($plugins as $plugin)
        <section class="wb-card">
            <div class="wb-card-body wb-stack wb-stack-3">
                <h2>{{ $plugin['label'] }} <small>{{ $plugin['version'] }}</small></h2>
                <p>{{ __('webblocks-cms::admin.plugin_recovery.'.($plugin['failed'] ? 'quarantined' : ($plugin['enabled'] ? 'enabled' : 'disabled'))) }}</p>
                <form method="POST" action="{{ route('admin.plugins.recovery.update', $plugin['handle']) }}" class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    @csrf
                    <button type="submit" name="operation" value="disable" class="wb-btn wb-btn-secondary">{{ __('webblocks-cms::admin.plugin_recovery.disable') }}</button>
                    @if(($plugin['previous']['rollback_safe'] ?? false) === true)
                        <button type="submit" name="operation" value="restore" class="wb-btn wb-btn-primary">{{ __('webblocks-cms::admin.plugin_recovery.restore', ['version' => $plugin['previous']['version']]) }}</button>
                    @elseif(!empty($plugin['previous']))
                        <span class="wb-text-muted">{{ __('webblocks-cms::admin.plugin_recovery.restore_unsafe') }}</span>
                    @endif
                </form>
            </div>
        </section>
    @empty
        <p>{{ __('webblocks-cms::admin.plugin_recovery.empty') }}</p>
    @endforelse
    <a href="{{ route('admin.dashboard') }}" class="wb-btn wb-btn-secondary">{{ __('webblocks-cms::admin.plugin_recovery.return') }}</a>
</div>
@endsection
