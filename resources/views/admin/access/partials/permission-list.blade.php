@php
    $readOnly = $readOnly ?? false;
    $visiblePermissions = $readOnly ? array_intersect_key($permissions, array_flip($selectedPermissions)) : $permissions;
    $permissionGroups = collect($visiblePermissions)->groupBy('group', preserveKeys: true);
    $selectedCount = count(array_intersect($selectedPermissions, array_keys($visiblePermissions)));
@endphp

<div class="wb-field wb-api-token-capabilities" @if (! $readOnly) data-wb-capability-scope data-wb-capability-field="permissions[]" @endif>
    <div class="wb-stack wb-stack-3">
        <div class="wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div class="wb-stack wb-stack-1">
                <div class="wb-label">{{ __('webblocks-cms::acl.permissions_title') }}</div>
                <div class="wb-text-sm wb-text-muted">{{ __('webblocks-cms::acl.'.($readOnly ? 'permissions_readonly_help' : 'live_help')) }}</div>
            </div>
            <span class="wb-status-pill wb-status-info" @if (! $readOnly) data-wb-capability-total data-wb-capability-total-template="{{ __('webblocks-cms::acl.permissions_selected', ['selected' => '__SELECTED__', 'total' => '__TOTAL__']) }}" @endif>{{ $readOnly ? __('webblocks-cms::acl.permissions_granted', ['count' => $selectedCount]) : __('webblocks-cms::acl.permissions_selected', ['selected' => $selectedCount, 'total' => count($visiblePermissions)]) }}</span>
        </div>
        @if ($permissionGroups->isEmpty())
            <div class="wb-empty wb-empty-sm"><div class="wb-empty-title">{{ __('webblocks-cms::acl.permissions_empty') }}</div></div>
        @else
            <div class="wb-api-token-capability-groups wb-items-start">
                @foreach ($permissionGroups as $group => $items)
                    @php
                        $groupId = $permissionPrefix.'-group-'.$loop->index;
                        $groupSelectedCount = count(array_intersect($selectedPermissions, $items->keys()->all()));
                    @endphp
                    <details class="wb-api-token-capability-group" @if (! $readOnly) data-wb-capability-group @endif>
                        <summary class="wb-api-token-capability-summary" id="{{ $groupId }}">
                            <span class="wb-api-token-capability-summary-copy"><strong>{{ __('webblocks-cms::acl.groups.'.$group) }}</strong></span>
                            <span class="wb-status-pill {{ $groupSelectedCount > 0 ? 'wb-status-active' : 'wb-status-info' }}" @if (! $readOnly) data-wb-capability-group-count @endif>{{ $readOnly ? $items->count() : $groupSelectedCount.'/'.$items->count() }}</span>
                        </summary>
                        <div class="wb-api-token-capability-list" aria-labelledby="{{ $groupId }}">
                            @foreach ($items as $key => $definition)
                                @if ($readOnly)
                                    <div class="wb-check wb-api-token-capability-option">
                                        <i class="wb-icon wb-icon-check wb-text-success" aria-hidden="true"></i>
                                        <span class="wb-api-token-capability-copy"><strong>{{ $key }}</strong><span class="wb-text-sm wb-text-muted">{{ $definition['label'] }}</span></span>
                                    </div>
                                @else
                                    @php($permissionId = $permissionPrefix.'-permission-'.sha1($key))
                                    <label class="wb-check wb-api-token-capability-option" for="{{ $permissionId }}">
                                        <input id="{{ $permissionId }}" type="checkbox" name="permissions[]" value="{{ $key }}" @checked(in_array($key, $selectedPermissions, true))>
                                        <span class="wb-api-token-capability-copy"><strong>{{ $key }}</strong><span class="wb-text-sm wb-text-muted">{{ $definition['label'] }}</span></span>
                                    </label>
                                @endif
                            @endforeach
                        </div>
                    </details>
                @endforeach
            </div>
        @endif
    </div>
</div>
