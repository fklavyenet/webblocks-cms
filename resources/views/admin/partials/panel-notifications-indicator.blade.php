@if ($panelNotifications !== null)
    <a href="{{ route('admin.dashboard') }}#panel-notifications" class="wb-btn wb-btn-ghost wb-btn-icon" data-wb-panel-notifications
        aria-label="{{ __('webblocks-cms::notifications.panel_indicator', ['unread' => $panelNotifications['unread'], 'awaiting' => $panelNotifications['awaiting'], 'warnings' => $panelNotifications['warnings']]) }}"
        title="{{ __('webblocks-cms::notifications.panel_title') }}">
        <i class="wb-icon wb-icon-bell" aria-hidden="true"></i>
        @if ($panelNotifications['attention'] > 0)
            <span class="wb-btn-badge" aria-hidden="true">{{ $panelNotifications['attention'] > 99 ? '99+' : $panelNotifications['attention'] }}</span>
        @endif
        <span class="wb-sr-only">{{ __('webblocks-cms::notifications.panel_title') }}</span>
    </a>
@endif
