<div class="wb-modal wb-modal-lg" id="acl-event-{{ $event->id }}" role="dialog" aria-modal="true" aria-labelledby="acl-event-{{ $event->id }}-title">
    <div class="wb-modal-dialog">
        <div class="wb-modal-header">
            <h2 class="wb-modal-title" id="acl-event-{{ $event->id }}-title">{{ __('webblocks-cms::acl.history_details') }}</h2>
            <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ __('webblocks-cms::acl.close') }}"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></button>
        </div>
        <div class="wb-modal-body">
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped"><tbody>
                    @foreach (['time' => $eventTime, 'actor' => $eventActor, 'action' => $eventAction, 'role' => $event->role_name, 'user' => $event->user_name, 'site' => $event->site_name, 'permission' => $event->permission, 'before' => $event->before_value, 'after' => $event->after_value] as $label => $value)
                        <tr><th scope="row" class="wb-table-key">{{ __('webblocks-cms::acl.'.$label) }}</th><td>{{ in_array($label, ['before', 'after'], true) && in_array($event->action, ['user.access.saved', 'user.activity.changed', 'user.system_role.changed'], true) && in_array($value, ['active', 'inactive', 'super_admin', 'site_roles'], true) ? __('webblocks-cms::acl.event_values.'.$value) : ($value ?? '—') }}</td></tr>
                    @endforeach
                </tbody></table>
            </div>
        </div>
        <div class="wb-modal-footer"><button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ __('webblocks-cms::acl.close') }}</button></div>
    </div>
</div>
