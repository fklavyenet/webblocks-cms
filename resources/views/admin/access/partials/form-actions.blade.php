<div class="wb-action-group">
    <a class="wb-btn wb-btn-secondary" href="{{ route('admin.access.roles.index') }}">{{ __('webblocks-cms::acl.back_to_roles') }}</a>
    @if ($role->exists)
        <a class="wb-btn wb-btn-secondary" href="{{ route('admin.access.roles.create', ['copy' => $role->id]) }}">{{ __('webblocks-cms::acl.copy') }}</a>
    @endif
</div>
