@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $adminLocale = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('contact_messages_show.'.$key, $adminLocale, $replace);
    $statusLabel = static fn (string $status) => $adminText('status_'.$status);
    $pageTitle = $adminText('title', ['name' => $message->detailTitleName()]);
    $notificationStatus = $message->resolvedNotificationStatus();
    $notificationLabel = $adminTranslator->admin('contact_messages_index.notification_'.$notificationStatus, $adminLocale);
    $primaryStatuses = ['replied', 'archived'];
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $pageTitle,
        'actions' => '<a href="'.route('admin.contact-messages.index').'" class="wb-btn wb-btn-secondary">'.e($adminText('back_to_inbox')).'</a>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    @if (in_array($message->status, ['spam', 'quarantined'], true))
        <div class="wb-alert wb-alert-warning">{{ $adminText('review_warning') }}</div>
    @endif

    @if (in_array($notificationStatus, ['failed', 'not_configured'], true))
        <div class="wb-alert wb-alert-warning">{{ $adminText('notification_warning') }}</div>
    @endif

    <div class="wb-card wb-contact-message-detail">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
            <strong>{{ $adminText('visitor_message') }}</strong>
            <span class="wb-status-pill {{ $message->statusClass() }}">{{ $statusLabel($message->status) }}</span>
        </div>
        <div class="wb-card-body">
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped">
                    <tbody>
                        <tr><th scope="row" class="wb-table-key">{{ $adminText('name') }}</th><td>{{ $message->name }}</td></tr>
                        <tr><th scope="row" class="wb-table-key">{{ $adminText('email') }}</th><td><a href="mailto:{{ $message->email }}" class="wb-link">{{ $message->email }}</a></td></tr>
                        <tr><th scope="row" class="wb-table-key">{{ $adminText('subject') }}</th><td>{{ $message->subject ?? $adminText('empty_value') }}</td></tr>
                        <tr><th scope="row" class="wb-table-key">{{ $adminText('message') }}</th><td><div class="wb-contact-message-body">{{ $message->message }}</div></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="wb-card-footer wb-cluster wb-cluster-between wb-cluster-2">
            <div class="wb-cluster wb-cluster-2">
                <a href="mailto:{{ $message->email }}?subject={{ rawurlencode($adminText('reply_subject', ['subject' => $message->subject ?? ''])) }}" class="wb-btn wb-btn-primary">{{ $adminText('reply_by_email') }}</a>
                @foreach ($primaryStatuses as $status)
                    @if ($message->status !== $status)
                        <form method="POST" action="{{ route('admin.contact-messages.status', $message) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="{{ $status }}">
                            <button type="submit" class="wb-btn wb-btn-secondary">{{ $adminText('mark_'.$status) }}</button>
                        </form>
                    @endif
                @endforeach
            </div>
            <div class="wb-dropdown wb-dropdown-end">
                <button type="button" class="wb-btn wb-btn-secondary" data-wb-toggle="dropdown" data-wb-target="#contact-message-actions" aria-expanded="false" aria-controls="contact-message-actions">
                    {{ $adminText('more_actions') }}
                    <i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i>
                </button>
                <div class="wb-dropdown-menu" id="contact-message-actions">
                    @foreach ($statuses as $status)
                        @if ($message->status !== $status && ! in_array($status, $primaryStatuses, true))
                            <form method="POST" action="{{ route('admin.contact-messages.status', $message) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="status" value="{{ $status }}">
                                <button type="submit" class="wb-dropdown-item">{{ $adminText('mark_status', ['status' => $statusLabel($status)]) }}</button>
                            </form>
                        @endif
                    @endforeach
                    <hr class="wb-dropdown-divider">
                    <button type="button" class="wb-dropdown-item wb-text-danger" data-wb-toggle="modal" data-wb-target="#delete-contact-message-modal" aria-haspopup="dialog">{{ $adminText('delete') }}</button>
                </div>
            </div>
        </div>
    </div>

    <details class="wb-card wb-contact-message-detail" id="contact-message-details">
        <summary class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
            <strong>{{ $adminText('technical_details') }}</strong>
            <i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i>
        </summary>
        <div class="wb-card-body wb-stack wb-gap-3">
            <section class="wb-stack wb-gap-2" aria-labelledby="contact-submission-heading">
                <h3 id="contact-submission-heading" class="wb-contact-message-subject">{{ $adminText('submission_details') }}</h3>
                <div class="wb-table-wrap">
                    <table class="wb-table">
                        <tbody>
                            <tr><th scope="row" class="wb-table-key">{{ $adminText('page_label') }}</th><td>{{ $message->page?->title ?? '-' }}</td></tr>
                            <tr><th scope="row" class="wb-table-key">{{ $adminText('source_url_label') }}</th><td>@if ($message->source_url)<a href="{{ $message->source_url }}" target="_blank" rel="noopener noreferrer" class="wb-link">{{ $message->sourcePath() }}</a>@else - @endif</td></tr>
                            <tr><th scope="row" class="wb-table-key">{{ $adminText('referrer_label') }}</th><td>{{ $message->referer ?? '-' }}</td></tr>
                            <tr><th scope="row" class="wb-table-key">{{ $adminText('received_at_label') }}</th><td>{{ $message->created_at?->format('Y-m-d H:i:s') }}</td></tr>
                            <tr><th scope="row" class="wb-table-key">{{ $adminText('block_slot_label') }}</th><td>{{ $message->block?->typeName() ?? '-' }} / {{ $message->block?->slotType?->name ?? $message->block?->slotName() ?? '-' }}</td></tr>
                            <tr><th scope="row" class="wb-table-key">{{ $adminText('ip_address_label') }}</th><td>{{ $message->ip_address ?? '-' }}</td></tr>
                            <tr><th scope="row" class="wb-table-key">{{ $adminText('user_agent_label') }}</th><td>{{ $message->user_agent ?? '-' }}</td></tr>
                            <tr><th scope="row" class="wb-table-key">{{ $adminText('block_id_label') }}</th><td>{{ $message->block_id ? '#'.$message->block_id : '-' }}</td></tr>
                            <tr><th scope="row" class="wb-table-key">{{ $adminText('page_id_label') }}</th><td>{{ $message->page_id ? '#'.$message->page_id : '-' }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="wb-stack wb-gap-2" aria-labelledby="contact-notification-heading">
                <h3 id="contact-notification-heading" class="wb-contact-message-subject">{{ $adminText('email_notification') }}</h3>
                <div class="wb-table-wrap">
                    <table class="wb-table">
                        <tbody>
                            <tr><th scope="row" class="wb-table-key">{{ $adminText('status_label') }}</th><td><span class="wb-status-pill {{ $message->notificationClass() }}">{{ $notificationLabel }}</span></td></tr>
                            <tr><th scope="row" class="wb-table-key">{{ $adminText('recipient_label') }}</th><td>{{ $message->notification_recipient ?? '-' }}</td></tr>
                            <tr><th scope="row" class="wb-table-key">{{ $adminText('recipient_source_label') }}</th><td>{{ $message->notificationSourceLabel() }}</td></tr>
                            <tr><th scope="row" class="wb-table-key">{{ $adminText('attempted_sent_at_label') }}</th><td>{{ $message->notification_sent_at?->format('Y-m-d H:i:s') ?? '-' }}</td></tr>
                            @if (filled($message->notificationDetail()))
                                <tr><th scope="row" class="wb-table-key">{{ $adminText('failure_or_skipped_reason_label') }}</th><td>{{ $message->notificationDetail() }}</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
                @if ($message->hasLegacyNotificationState())
                    <div class="wb-text-sm wb-text-muted">{{ $adminText('legacy_notification_state') }}</div>
                @endif
                <p class="wb-text-sm wb-text-muted">{{ $adminText('delivery_help') }}</p>
            </section>

            <section class="wb-stack wb-gap-2" aria-labelledby="contact-classification-heading">
                <h3 id="contact-classification-heading" class="wb-contact-message-subject">{{ $adminText('message_classification') }}</h3>
                <div class="wb-table-wrap">
                    <table class="wb-table">
                        <tbody>
                            <tr><th scope="row" class="wb-table-key">{{ $adminText('spam_score_label') }}</th><td>{{ $message->spam_score ?? 0 }}</td></tr>
                            <tr><th scope="row" class="wb-table-key">{{ $adminText('spam_signals_label') }}</th><td>{{ $message->spamReasonLabels() === [] ? '-' : implode(', ', $message->spamReasonLabels()) }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>
    </details>
@endsection

@push('overlays')
    @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
        'id' => 'delete-contact-message-modal',
        'title' => $adminText('delete_contact_message'),
        'description' => $adminText('delete_contact_message_description'),
        'action' => route('admin.contact-messages.destroy', $message),
        'method' => 'DELETE',
        'submitLabel' => $adminText('delete_message'),
    ])
        <div class="wb-card wb-card-muted">
            <div class="wb-card-body wb-stack wb-gap-2">
                <div><strong>{{ $message->subject ?: $adminText('contact_message_number', ['id' => $message->id]) }}</strong></div>
                <div class="wb-text-sm wb-text-muted">{{ $adminText('from_sender', ['name' => $message->name, 'email' => $message->email]) }}</div>
            </div>
        </div>

        <p class="wb-text-sm wb-text-muted">{{ $adminText('cannot_be_undone') }}</p>
    @endcomponent
@endpush
