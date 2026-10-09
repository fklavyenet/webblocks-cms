{{ __('webblocks-cms::notifications.'.($summary === null ? 'new_body' : 'summary_body'), ['count' => $messageCount, 'unread' => $summary['unread'] ?? 0, 'awaiting' => $summary['awaiting'] ?? 0]) }}

{{ __('webblocks-cms::notifications.open_inbox') }}: {{ $inboxUrl }}
@foreach ($summary['links'] ?? [] as $link)
{{ __($link['label']) }} — {{ __('webblocks-cms::notifications.summary_body', ['unread' => $link['unread'], 'awaiting' => $link['awaiting']]) }}: {{ $link['url'] }}
@endforeach
{{ __('webblocks-cms::notifications.login_required') }}
