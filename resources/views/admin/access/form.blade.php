@php
    $errors ??= new \Illuminate\Support\ViewErrorBag;
    $rolePageTitle = $role->exists
        ? __('webblocks-cms::acl.role_title', ['name' => $role->name])
        : ($source ? __('webblocks-cms::acl.copy_title', ['name' => $source->name]) : __('webblocks-cms::acl.new'));
    $selectedPermissions = (array) old('permissions', session()->hasOldInput() ? [] : $selected);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $rolePageTitle, 'heading' => $rolePageTitle])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $rolePageTitle,
        'description' => __('webblocks-cms::acl.form_description'),
        'actions' => view('webblocks-cms::admin.access.partials.form-actions', ['role' => $role])->render(),
    ])
    @include('webblocks-cms::admin.partials.flash')

    @if ($role->is_protected)
        <div class="wb-alert wb-alert-info">{{ __('webblocks-cms::acl.compatibility_help') }}</div>
    @endif

    <form method="POST" action="{{ $role->exists ? route('admin.access.roles.update', $role) : route('admin.access.roles.store') }}" class="wb-card">
        <div class="wb-card-header"><strong>{{ $rolePageTitle }}</strong></div>
        @csrf
        @if ($role->exists)
            @method('PUT')
            <input type="hidden" name="version" value="{{ $role->version }}">
        @endif

        <div class="wb-card-body wb-stack wb-gap-4">
            <div class="wb-field">
                <label class="wb-label" for="acl_name">{{ __('webblocks-cms::acl.name') }}</label>
                <input class="wb-input" id="acl_name" name="name" value="{{ old('name', $role->name ?? $source?->name) }}" required maxlength="160" @disabled($role->is_protected) @if ($errors->has('name')) aria-invalid="true" aria-describedby="acl-name-error" @endif>
                @error('name')<div class="wb-field-error" id="acl-name-error">{{ $message }}</div>@enderror
            </div>
            <div class="wb-field">
                <label class="wb-label" for="acl_description">{{ __('webblocks-cms::acl.details') }}</label>
                <textarea class="wb-textarea" id="acl_description" name="description" maxlength="2000" rows="3" @disabled($role->is_protected) @if ($errors->has('description')) aria-invalid="true" aria-describedby="acl-description-error" @endif>{{ old('description', $role->description ?? $source?->description) }}</textarea>
                @error('description')<div class="wb-field-error" id="acl-description-error">{{ $message }}</div>@enderror
            </div>
            @include('webblocks-cms::admin.access.partials.permission-list', [
                'permissionPrefix' => 'acl-role-form', 'readOnly' => (bool) $role->is_protected,
            ])
            @error('permissions')<div class="wb-field-error">{{ $message }}</div>@enderror
            @error('permissions.*')<div class="wb-field-error">{{ $message }}</div>@enderror
        </div>
        <div class="wb-card-footer">
            <div class="wb-action-group">
                @if (! $role->is_protected)<button class="wb-btn wb-btn-primary" type="submit">{{ __('webblocks-cms::acl.save') }}</button>@endif
                <a class="wb-btn wb-btn-secondary" href="{{ route('admin.access.roles.index') }}">{{ __('webblocks-cms::acl.cancel') }}</a>
            </div>
        </div>
    </form>

    @if ($role->exists)
        <section class="wb-card" aria-labelledby="acl-role-assignments-title">
            <div class="wb-card-header">
                <h2 class="wb-card-title" id="acl-role-assignments-title">{{ __('webblocks-cms::acl.assignments') }}</h2>
                <span class="wb-status-pill wb-status-info">{{ $assignments->count() }}</span>
            </div>
            <div class="wb-card-body">
                @if ($assignments->isEmpty())
                    <div class="wb-empty wb-empty-sm"><div class="wb-empty-title">{{ __('webblocks-cms::acl.assignments_empty') }}</div></div>
                @else
                    <div class="wb-table-wrap"><table class="wb-table wb-table-striped">
                        <thead><tr><th scope="col">{{ __('webblocks-cms::acl.user') }}</th><th scope="col">{{ __('webblocks-cms::acl.site') }}</th></tr></thead>
                        <tbody>@foreach ($assignments as $assignment)<tr><td>{{ $assignment->user_name }}</td><td>{{ $assignment->site_name }}</td></tr>@endforeach</tbody>
                    </table></div>
                @endif
            </div>
            @if (! $role->is_protected && $assignments->isEmpty())
                <div class="wb-card-footer"><div class="wb-action-group">
                    <a class="wb-btn wb-btn-danger" href="{{ route('admin.access.roles.edit', ['cmsRole' => $role, 'delete' => 1]) }}">{{ __('webblocks-cms::acl.delete') }}</a>
                </div></div>
            @endif
        </section>
        @if (! $role->is_protected && $assignments->isEmpty() && request()->boolean('delete'))
            @push('overlays')
                <div class="wb-modal" id="acl-delete-modal" role="dialog" aria-modal="true" aria-labelledby="acl-delete-title" data-wb-admin-close-url="{{ route('admin.access.roles.edit', $role) }}" data-wb-admin-autoload-overlay hidden>
                    <div class="wb-modal-dialog">
                        <div class="wb-modal-header">
                            <h2 id="acl-delete-title" class="wb-modal-title">{{ __('webblocks-cms::acl.delete_title', ['name' => $role->name]) }}</h2>
                            <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ __('webblocks-cms::acl.close') }}"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></button>
                        </div>
                        <div class="wb-modal-body"><p>{{ __('webblocks-cms::acl.delete_help') }}</p></div>
                        <div class="wb-modal-footer">
                            <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ __('webblocks-cms::acl.cancel') }}</button>
                            <form method="POST" action="{{ route('admin.access.roles.destroy', $role) }}">@csrf @method('DELETE')<input type="hidden" name="version" value="{{ $role->version }}"><button type="submit" class="wb-btn wb-btn-danger">{{ __('webblocks-cms::acl.delete') }}</button></form>
                        </div>
                    </div>
                </div>
            @endpush
        @endif
    @endif
@endsection

@push('scripts')
    @include('webblocks-cms::admin.partials.admin-script', ['path' => 'cms/js/admin/api-token-capabilities.js'])
@endpush
