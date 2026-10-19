<div class="wb-modal wb-modal-lg" id="acl-role-{{ $role->id }}" role="dialog" aria-modal="true" aria-labelledby="acl-role-{{ $role->id }}-title">
    <div class="wb-modal-dialog">
        <div class="wb-modal-header">
            <div class="wb-stack wb-stack-1">
                <h2 class="wb-modal-title" id="acl-role-{{ $role->id }}-title">{{ $role->name }}</h2>
                <span class="wb-text-sm wb-text-muted">{{ __('webblocks-cms::acl.protected') }}</span>
            </div>
            <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ __('webblocks-cms::acl.close') }}"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></button>
        </div>
        <div class="wb-modal-body wb-stack wb-stack-4">
            <div class="wb-alert wb-alert-info">{{ __('webblocks-cms::acl.compatibility_help') }}</div>
            @if ($role->description)<p>{{ $role->description }}</p>@endif
            <p>{{ __('webblocks-cms::acl.assignments') }}: <strong>{{ $role->assignment_count }}</strong></p>
            @foreach (collect($rolePermissions)->map(fn ($key) => $permissions[$key] ?? null)->filter()->groupBy('group') as $group => $items)
                <section class="wb-stack wb-stack-2">
                    <h3 class="wb-text-sm">{{ __('webblocks-cms::acl.groups.'.$group) }}</h3>
                    <ul>@foreach ($items as $permission)<li>{{ $permission['label'] }}</li>@endforeach</ul>
                </section>
            @endforeach
        </div>
        <div class="wb-modal-footer">
            <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ __('webblocks-cms::acl.close') }}</button>
            <a class="wb-btn wb-btn-primary" href="{{ route('admin.access.roles.create', ['copy' => $role->id]) }}">{{ __('webblocks-cms::acl.copy') }}</a>
        </div>
    </div>
</div>
