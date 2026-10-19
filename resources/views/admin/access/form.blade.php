@extends('webblocks-cms::layouts.admin', ['title' => __('webblocks-cms::acl.title'), 'heading' => __('webblocks-cms::acl.title')])
@section('content')
    @include('webblocks-cms::admin.partials.flash')
    <div class="wb-stack wb-gap-4">
        <h1>{{ __('webblocks-cms::acl.title') }}</h1>
        <p>{{ __('webblocks-cms::acl.description') }}</p>
        <p class="wb-text-muted">{{ __('webblocks-cms::acl.live_help') }}</p>
        @if ($role->is_protected)<p class="wb-alert wb-alert-info">{{ __('webblocks-cms::acl.compatibility_help') }}</p>@endif
        @php($selectedPermissions = (array) old('permissions', session()->hasOldInput() ? [] : $selected))
        <form method="POST" action="{{ $role->exists ? route('admin.access.roles.update', $role) : route('admin.access.roles.store') }}" class="wb-stack wb-gap-4">
            @csrf
            @if ($role->exists) @method('PUT') <input type="hidden" name="version" value="{{ $role->version }}"> @endif
            <fieldset class="wb-stack wb-gap-3" @disabled($role->is_protected)>
                <label for="acl_name">{{ __('webblocks-cms::acl.name') }}</label>
                <input class="wb-input" id="acl_name" name="name" value="{{ old('name', $role->name ?? $source?->name) }}" required maxlength="160">
                <label for="acl_description">{{ __('webblocks-cms::acl.details') }}</label>
                <textarea class="wb-textarea" id="acl_description" name="description" maxlength="2000">{{ old('description', $role->description ?? $source?->description) }}</textarea>
                @foreach (collect($permissions)->groupBy('group', preserveKeys: true) as $group => $items)
                    <fieldset class="wb-card"><legend>{{ __('webblocks-cms::acl.groups.'.$group) }}</legend><div class="wb-card-body wb-stack wb-gap-2">
                    @foreach ($items as $key => $definition)
                        <label><input type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key, $selectedPermissions, true))> {{ $definition['label'] }}</label>
                    @endforeach
                    </div></fieldset>
                @endforeach
                <button class="wb-btn wb-btn-primary" type="submit">{{ __('webblocks-cms::acl.save') }}</button>
            </fieldset>
            <a class="wb-btn wb-btn-secondary" href="{{ route('admin.access.roles.index') }}">{{ __('webblocks-cms::acl.cancel') }}</a>
        </form>
        @if ($role->exists)
            <h2>{{ __('webblocks-cms::acl.assignments') }} ({{ $assignments->count() }})</h2>
            @foreach ($assignments as $assignment)<p>{{ $assignment->user_name }} · {{ $assignment->site_name }}</p>@endforeach
            <a class="wb-btn wb-btn-secondary" href="{{ route('admin.access.roles.create', ['copy' => $role->id]) }}">{{ __('webblocks-cms::acl.copy') }}</a>
            @if (! $role->is_protected && $assignments->isEmpty())
                <a class="wb-btn wb-btn-danger" href="{{ route('admin.access.roles.edit', ['cmsRole' => $role, 'delete' => 1]) }}">{{ __('webblocks-cms::acl.delete') }}</a>
                @if (request()->boolean('delete'))
                    <div class="wb-modal" id="acl-delete-modal" role="dialog" aria-modal="true" aria-labelledby="acl-delete-title" data-wb-admin-close-url="{{ route('admin.access.roles.edit', $role) }}" data-wb-admin-autoload-overlay hidden><div class="wb-modal-dialog">
                        <div class="wb-modal-header"><h2 id="acl-delete-title" class="wb-modal-title">{{ __('webblocks-cms::acl.delete') }}</h2></div>
                        <div class="wb-modal-body"><p>{{ __('webblocks-cms::acl.delete_help') }}</p></div>
                        <div class="wb-modal-footer"><form method="POST" action="{{ route('admin.access.roles.destroy', $role) }}">@csrf @method('DELETE')<input type="hidden" name="version" value="{{ $role->version }}"><button type="submit" class="wb-btn wb-btn-danger">{{ __('webblocks-cms::acl.delete') }}</button></form><a class="wb-btn wb-btn-secondary" data-wb-dismiss="modal" href="{{ route('admin.access.roles.edit', $role) }}">{{ __('webblocks-cms::acl.cancel') }}</a></div>
                    </div></div>
                @endif
            @endif
        @endif
    </div>
@endsection
