@php
    $unreadMessages = $panelNotifications['unread'] ?? 0;
    $pendingComments = $panelPendingComments ?? 0;
    $pluginNotifications = $panelPluginNotifications ?? [];
    $pendingItems = $unreadMessages + $pendingComments + array_sum(array_column($pluginNotifications, 'count'));
    $pluginUpdateCount = $panelPluginUpdateCount ?? 0;
    $total = $pendingItems + $pluginUpdateCount;
    $checkUpdates = ($checkPanelPluginUpdates ?? false) && Route::has('admin.system.plugins.update-indicator');
    $notificationLabel = static fn (string $key, int $count) => trans_choice('webblocks-cms::notifications.'.$key, $count, [], $adminLocale ?? null);
@endphp

@if ($total > 0 || $checkUpdates)
    <div class="wb-dropdown wb-dropdown-end" @if ($total === 0) hidden @endif
        @if ($checkUpdates)
            data-wb-plugin-update-check="{{ route('admin.system.plugins.update-indicator') }}"
            data-pending-items="{{ $pendingItems }}"
            data-single-label="{{ $notificationLabel('panel_pending_items', 1) }}"
            data-total-label="{{ trans_choice('webblocks-cms::notifications.panel_pending_items', 2, ['count' => '__count__'], $adminLocale ?? null) }}"
        @endif>
        <button type="button" class="wb-btn wb-btn-ghost wb-btn-icon" data-wb-panel-notifications
            data-wb-toggle="dropdown" data-wb-target="#admin-panel-notifications" aria-controls="admin-panel-notifications" aria-expanded="false"
            aria-label="{{ $notificationLabel('panel_pending_items', $total) }}" data-wb-tooltip="{{ $notificationLabel('panel_pending_items', $total) }}">
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
            @if ($pluginUpdateCount > 0 || $checkUpdates)
                <a class="wb-dropdown-item" data-wb-plugin-update-link href="{{ route('admin.system.plugins.index') }}" @if ($pluginUpdateCount === 0) hidden @endif>
                    {{ $notificationLabel('panel_plugin_updates', $pluginUpdateCount) }}
                </a>
            @endif
        </div>
    </div>
@endif
