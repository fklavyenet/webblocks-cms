<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head><meta charset="utf-8"></head>
<body>
<p>{{ __('webblocks-cms::notifications.'.($summary === null ? 'new_body' : 'summary_body'), ['count' => $messageCount, 'unread' => $summary['unread'] ?? 0, 'awaiting' => $summary['awaiting'] ?? 0]) }}</p>
<p><a href="{{ $inboxUrl }}">{{ __('webblocks-cms::notifications.open_inbox') }}</a></p>
@foreach ($summary['links'] ?? [] as $link)
<p><a href="{{ $link['url'] }}">{{ __($link['label']) }} — {{ __('webblocks-cms::notifications.summary_body', ['unread' => $link['unread'], 'awaiting' => $link['awaiting']]) }}</a></p>
@endforeach
<p>{{ __('webblocks-cms::notifications.login_required') }}</p>
</body>
</html>
