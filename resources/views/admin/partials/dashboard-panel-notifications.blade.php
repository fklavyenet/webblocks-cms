@if ($panelNotifications !== null)
    <section class="wb-card" data-wb-panel-notification-summary>
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
            <strong>{{ __('webblocks-cms::notifications.panel_title') }}</strong>
            <a href="{{ route('admin.contact-messages.index') }}" class="wb-btn wb-btn-secondary">{{ __('webblocks-cms::notifications.open_inbox') }}</a>
        </div>
        <div class="wb-card-body wb-stack wb-stack-3">
            <p>{{ __('webblocks-cms::notifications.panel_help') }}</p>
            @if (! $panelNotifications['inbox_available'])
                <div class="wb-alert wb-alert-warning">{{ __('webblocks-cms::notifications.panel_unavailable') }}</div>
            @elseif ($panelNotifications['awaiting'] === 0)
                <p class="wb-text-muted">{{ __('webblocks-cms::notifications.panel_empty') }}</p>
            @else
                <div class="wb-table-wrap">
                    <table class="wb-table">
                        <thead><tr>
                            <th scope="col">{{ __('webblocks-cms::notifications.panel_site') }}</th>
                            <th scope="col">{{ __('webblocks-cms::notifications.panel_unread') }}</th>
                            <th scope="col">{{ __('webblocks-cms::notifications.panel_awaiting') }}</th>
                            <th scope="col">{{ __('webblocks-cms::notifications.panel_actions') }}</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($panelNotifications['inboxes']->where('awaiting', '>', 0) as $inbox)
                                <tr>
                                    <th scope="row">{{ $inbox['site']->publicDisplayName() ?: $inbox['site']->name }}</th>
                                    <td>{{ $inbox['unread'] }}</td><td>{{ $inbox['awaiting'] }}</td>
                                    <td class="wb-table-actions">
                                        <div class="wb-action-group">
                                            <a class="wb-action-btn wb-action-btn-view" href="{{ $inbox['url'] }}"
                                                title="{{ __('webblocks-cms::notifications.panel_review_awaiting') }}"
                                                aria-label="{{ __('webblocks-cms::notifications.panel_review_awaiting') }}">
                                                <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <p class="wb-text-sm wb-text-muted">{{ __('webblocks-cms::notifications.panel_delivery_help') }}</p>
        </div>
    </section>
@endif
