@extends('webblocks-cms::layouts.admin', ['title' => __('webblocks-cms::acl.title'), 'heading' => __('webblocks-cms::acl.title')])
@section('content')
    @include('webblocks-cms::admin.partials.flash')
    <div class="wb-stack wb-gap-4">
        <h1>{{ __('webblocks-cms::acl.title') }}</h1>
        <p>{{ __('webblocks-cms::acl.description') }}</p>
        <div class="wb-cluster wb-gap-2">
            <a class="wb-btn wb-btn-primary" href="{{ route('admin.access.roles.create') }}">{{ __('webblocks-cms::acl.new') }}</a>
            <a class="wb-btn wb-btn-secondary" href="{{ route('admin.access.inspect') }}">{{ __('webblocks-cms::acl.inspect') }}</a>
        </div>
        <div class="wb-card"><div class="wb-card-body wb-table-wrap">
            <table class="wb-table"><thead><tr><th>{{ __('webblocks-cms::acl.name') }}</th><th>{{ __('webblocks-cms::acl.assignments') }}</th><th>{{ __('webblocks-cms::acl.action') }}</th></tr></thead><tbody>
            @foreach ($roles as $role)
                <tr><td><a href="{{ route('admin.access.roles.edit', $role) }}">{{ $role->name }}</a><p>{{ $role->is_protected ? __('webblocks-cms::acl.protected') : __('webblocks-cms::acl.custom') }}</p></td><td>{{ $role->assignment_count }}</td><td><a class="wb-btn wb-btn-secondary" href="{{ route('admin.access.roles.create', ['copy' => $role->id]) }}">{{ __('webblocks-cms::acl.copy') }}</a></td></tr>
            @endforeach
            </tbody></table>
        </div></div>
        <h2>{{ __('webblocks-cms::acl.history') }}</h2>
        <div class="wb-card"><div class="wb-card-body wb-table-wrap">
            <table class="wb-table"><thead><tr><th>{{ __('webblocks-cms::acl.time') }}</th><th>{{ __('webblocks-cms::acl.actor') }}</th><th>{{ __('webblocks-cms::acl.action') }}</th><th>{{ __('webblocks-cms::acl.details') }}</th></tr></thead><tbody>
            @foreach ($history as $event)
                <tr><td>{{ $event->created_at }}</td><td>{{ $event->actor_name ?? $event->actor_id }}</td><td>{{ __('webblocks-cms::acl.events.'.str_replace('.', '_', $event->action)) }}</td><td>{{ $event->permission ?? $event->after_value ?? $event->before_value }} · {{ $event->role_name }} · {{ $event->user_name }} · {{ $event->site_name }}</td></tr>
            @endforeach
            </tbody></table>
            {{ $history->links() }}
        </div></div>
    </div>
@endsection
