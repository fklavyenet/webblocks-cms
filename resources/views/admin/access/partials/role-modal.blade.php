<div class="wb-modal wb-modal-xl" id="acl-role-{{ $role->id }}" role="dialog" aria-modal="true" aria-labelledby="acl-role-{{ $role->id }}-title">
    <div class="wb-modal-dialog">
        <div class="wb-modal-header">
            <div class="wb-stack wb-stack-1">
                <h2 class="wb-modal-title" id="acl-role-{{ $role->id }}-title">{{ __('webblocks-cms::acl.role_title', ['name' => $role->name]) }}</h2>
                <span class="wb-text-sm wb-text-muted">{{ __('webblocks-cms::acl.protected') }}</span>
            </div>
            <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ __('webblocks-cms::acl.close') }}"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></button>
        </div>
        <div class="wb-modal-body wb-stack-4 wb-api-token-modal-body">
            <div class="wb-alert wb-alert-info">{{ __('webblocks-cms::acl.compatibility_help') }}</div>
            <div class="wb-cluster wb-cluster-between wb-flex-wrap">
                <div class="wb-stack wb-stack-1"><span class="wb-label">{{ __('webblocks-cms::acl.details') }}</span><span class="wb-text-sm wb-text-muted">{{ $role->description ?: __('webblocks-cms::acl.protected') }}</span></div>
                <span class="wb-status-pill wb-status-info">{{ __('webblocks-cms::acl.assignments') }}: {{ $role->assignment_count }}</span>
            </div>
            @include('webblocks-cms::admin.access.partials.permission-list', [
                'permissionPrefix' => 'acl-role-'.$role->id, 'selectedPermissions' => $rolePermissions, 'readOnly' => true,
            ])
        </div>
        <div class="wb-modal-footer">
            <a class="wb-btn wb-btn-primary" href="{{ route('admin.access.roles.create', ['copy' => $role->id]) }}">{{ __('webblocks-cms::acl.copy') }}</a>
            <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ __('webblocks-cms::acl.close') }}</button>
        </div>
    </div>
</div>
