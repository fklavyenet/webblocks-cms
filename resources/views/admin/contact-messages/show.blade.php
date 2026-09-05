@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $adminLocale = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('contact_messages_show.'.$key, $adminLocale, $replace);
    $statusLabel = static fn (string $status) => $adminText('status_'.$status);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => $adminText('description'),
        'actions' => '<div class="wb-cluster wb-cluster-2"><span class="wb-status-pill '.$message->statusClass().'">'.e($message->status).'</span><a href="'.route('admin.contact-messages.index').'" class="wb-btn wb-btn-secondary">'.$adminText('back_to_inbox').'</a></div>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-cluster wb-cluster-between wb-cluster-2">
        <div class="wb-cluster wb-cluster-2">
            @foreach ($statuses as $status)
                <form method="POST" action="{{ route('admin.contact-messages.status', $message) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ $status }}">
                    <button type="submit" class="wb-btn {{ $message->status === $status ? 'wb-btn-primary' : 'wb-btn-secondary' }}">{{ $adminText('mark_status', ['status' => $statusLabel($status)]) }}</button>
                </form>
            @endforeach
        </div>

        <button type="button" class="wb-btn wb-btn-danger" data-wb-toggle="modal" data-wb-target="#delete-contact-message-modal">{{ $adminText('delete') }}</button>
    </div>

    <div class="wb-card">
        <div class="wb-card-header"><strong>{{ $adminText('visitor_message') }}</strong></div>
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
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-card">
            <div class="wb-card-header"><strong>{{ $adminText('submission_details') }}</strong></div>
            <div class="wb-card-body">
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped">
                        <tbody>
                            <tr><th scope="row" class="wb-table-key">{{ $adminText('page_label') }}</th><td>{{ $message->page?->title ?? '-' }}</td></tr>
                            <tr><th scope="row" class="wb-table-key">{{ $adminText('path_label') }}</th><td><code>{{ $message->sourcePath() }}</code></td></tr>
                            <tr><th scope="row" class="wb-table-key">{{ $adminText('source_url_label') }}</th><td>@if ($message->source_url)<a href="{{ $message->source_url }}" target="_blank" rel="noopener noreferrer" class="wb-link">{{ $adminText('open_source') }}</a>@else - @endif</td></tr>
                            <tr><th scope="row" class="wb-table-key">{{ $adminText('referrer_label') }}</th><td>{{ $message->referer ?? '-' }}</td></tr>
                            <tr><th scope="row" class="wb-table-key">{{ $adminText('received_at_label') }}</th><td>{{ $message->created_at?->format('Y-m-d H:i:s') }}</td></tr>
                            <tr><th scope="row" class="wb-table-key">{{ $adminText('block_slot_label') }}</th><td>{{ $message->block?->typeName() ?? '-' }} / {{ $message->block?->slotType?->name ?? $message->block?->slotName() ?? '-' }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header"><strong>{{ $adminText('email_notification') }}</strong></div>
            <div class="wb-card-body wb-stack wb-gap-2">
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped">
                        <tbody>
                            <tr><th scope="row" class="wb-table-key">{{ $adminText('status_label') }}</th><td><span class="wb-status-pill {{ $message->notificationClass() }}">{{ $message->notificationLabel() }}</span></td></tr>
                            <tr><th scope="row" class="wb-table-key">{{ $adminText('recipient_label') }}</th><td>{{ $message->notification_recipient ?? '-' }}</td></tr>
                            <tr><th scope="row" class="wb-table-key">{{ $adminText('recipient_source_label') }}</th><td>{{ $message->notificationSourceLabel() }}</td></tr>
                            <tr><th scope="row" class="wb-table-key">{{ $adminText('attempted_sent_at_label') }}</th><td>{{ $message->notification_sent_at?->format('Y-m-d H:i:s') ?? '-' }}</td></tr>
                            <tr><th scope="row" class="wb-table-key">{{ $adminText('failure_or_skipped_reason_label') }}</th><td>{{ $message->notificationDetail() ?? '-' }}</td></tr>
                        </tbody>
                    </table>
                </div>
                @if ($message->hasLegacyNotificationState())
                    <div class="wb-alert wb-alert-info">{{ $adminText('legacy_notification_state') }}</div>
                @endif
                <div class="wb-text-sm wb-text-muted">{{ $adminText('delivery_help') }}</div>
                <div class="wb-divider"></div>
                <div class="wb-stack wb-gap-2">
                    <strong>{{ $adminText('setup_guidance') }}</strong>
                    <ul class="wb-text-sm wb-text-muted">
                        <li>{!! $adminText('setup_mail_html') !!}</li>
                        <li>{{ $adminText('setup_site_recipient') }}</li>
                        <li>{!! $adminText('setup_fallback_recipient_html') !!}</li>
                        <li>{!! $adminText('setup_diagnose_html', ['block' => $message->block_id ?? 'ID']) !!}</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="wb-card">
        <div class="wb-card-header"><strong>{{ $adminText('message_classification') }}</strong></div>
        <div class="wb-card-body wb-stack wb-gap-2">
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped">
                    <tbody>
                        <tr><th scope="row" class="wb-table-key">{{ $adminText('editorial_status_label') }}</th><td><span class="wb-status-pill {{ $message->statusClass() }}">{{ $message->status }}</span></td></tr>
                        <tr><th scope="row" class="wb-table-key">{{ $adminText('spam_score_label') }}</th><td>{{ $message->spam_score ?? 0 }}</td></tr>
                        <tr><th scope="row" class="wb-table-key">{{ $adminText('spam_signals_label') }}</th><td>@if ($message->spamReasonLabels() === []) - @else {{ implode(', ', $message->spamReasonLabels()) }} @endif</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="wb-text-sm wb-text-muted">{{ $adminText('classification_help') }}</div>
        </div>
    </div>

    <div class="wb-card wb-card-muted">
        <div class="wb-card-header"><strong>{{ $adminText('technical_details') }}</strong></div>
        <div class="wb-card-body wb-stack wb-gap-2">
            <div class="wb-text-sm wb-text-muted">{{ $adminText('technical_details_help') }}</div>
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped">
                    <tbody>
                        <tr><th scope="row" class="wb-table-key">{{ $adminText('ip_address_label') }}</th><td>{{ $message->ip_address ?? '-' }}</td></tr>
                        <tr><th scope="row" class="wb-table-key">{{ $adminText('user_agent_label') }}</th><td>{{ $message->user_agent ?? '-' }}</td></tr>
                        <tr><th scope="row" class="wb-table-key">{{ $adminText('block_id_label') }}</th><td>{{ $message->block_id ? '#'.$message->block_id : '-' }}</td></tr>
                        <tr><th scope="row" class="wb-table-key">{{ $adminText('page_id_label') }}</th><td>{{ $message->page_id ? '#'.$message->page_id : '-' }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
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
