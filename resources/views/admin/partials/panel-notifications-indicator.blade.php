@php
    $unreadMessages = $panelNotifications['unread'] ?? 0;
    $pendingComments = $panelPendingComments ?? 0;
    $pluginNotifications = $panelPluginNotifications ?? [];
    $total = $unreadMessages + $pendingComments + array_sum(array_column($pluginNotifications, 'count'));
    $notificationLabel = static fn (string $key, int $count) => trans_choice('webblocks-cms::notifications.'.$key, $count, [], $adminLocale ?? null);
@endphp

@if ($total > 0)
    <div class="wb-dropdown wb-dropdown-end">
        <button type="button" class="wb-btn wb-btn-ghost wb-btn-icon" data-wb-panel-notifications
            data-wb-toggle="dropdown" data-wb-target="#admin-panel-notifications" aria-controls="admin-panel-notifications" aria-expanded="false"
            aria-label="{{ $notificationLabel('panel_pending_items', $total) }}" title="{{ $notificationLabel('panel_pending_items', $total) }}">
            <i class="wb-icon wb-icon-bell" aria-hidden="true"></i>
            <span class="wb-btn-badge" aria-hidden="true">{{ $total > 99 ? '99+' : $total }}</span>
        </button>
        <div class="wb-dropdown-menu" id="admin-panel-notifications">
            <div class="wb-dropdown-label">{{ __('webblocks-cms::notifications.panel_title') }}</div>
            @if ($unreadMessages > 0)
                <a class="wb-dropdown-item" href="{{ route('admin.contact-messages.index', ['status' => 'new']) }}">
                    {{ $notificationLabel('panel_new_messages', $unreadMessages) }}
                </a>
            @endif
            @if ($pendingComments > 0)
                <a class="wb-dropdown-item" href="{{ route('admin.engagement.comments.index', ['status' => 'pending', 'moderatable' => 1]) }}">
                    {{ $notificationLabel('panel_pending_comments', $pendingComments) }}
                </a>
            @endif
            @foreach ($pluginNotifications as $notification)
                <a class="wb-dropdown-item" href="{{ $notification['url'] }}">
                    {{ trans_choice($notification['labelKey'], $notification['count'], [], $adminLocale ?? null) }}
                </a>
            @endforeach
        </div>
    </div>
@endif
