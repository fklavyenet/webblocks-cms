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
        <div class="wb-card-body wb-stack wb-gap-4">
            <dl class="wb-detail-list wb-contact-message-meta">
                <div class="wb-detail-row">
                    <dt class="wb-detail-label">{{ $adminText('name') }}</dt>
                    <dd class="wb-detail-value">{{ $message->name }}</dd>
                </div>
                <div class="wb-detail-row">
                    <dt class="wb-detail-label">{{ $adminText('email') }}</dt>
                    <dd class="wb-detail-value"><a href="mailto:{{ $message->email }}" class="wb-link">{{ $message->email }}</a></dd>
                </div>
                <div class="wb-detail-row">
                    <dt class="wb-detail-label">{{ $adminText('subject') }}</dt>
                    <dd class="wb-detail-value">{{ $message->subject ?? $adminText('empty_value') }}</dd>
                </div>
            </dl>
            <div class="wb-stack wb-gap-2">
                <strong>{{ $adminText('message') }}</strong>
                <div class="wb-contact-message-body">{{ $message->message }}</div>
            </div>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-card">
            <div class="wb-card-header"><strong>{{ $adminText('submission_details') }}</strong></div>
            <div class="wb-card-body wb-stack wb-gap-2">
                <div><strong>{{ $adminText('page_label') }}</strong> {{ $message->page?->title ?? '-' }}</div>
                <div><strong>{{ $adminText('path_label') }}</strong> <code>{{ $message->sourcePath() }}</code></div>
                <div><strong>{{ $adminText('source_url_label') }}</strong> @if ($message->source_url)<a href="{{ $message->source_url }}" target="_blank" rel="noopener noreferrer" class="wb-link">{{ $adminText('open_source') }}</a>@else - @endif</div>
                <div><strong>{{ $adminText('referrer_label') }}</strong> {{ $message->referer ?? '-' }}</div>
                <div><strong>{{ $adminText('received_at_label') }}</strong> {{ $message->created_at?->format('Y-m-d H:i:s') }}</div>
                <div><strong>{{ $adminText('block_slot_label') }}</strong> {{ $message->block?->typeName() ?? '-' }} / {{ $message->block?->slotType?->name ?? $message->block?->slotName() ?? '-' }}</div>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header"><strong>{{ $adminText('email_notification') }}</strong></div>
            <div class="wb-card-body wb-stack wb-gap-2">
                <div><strong>{{ $adminText('status_label') }}</strong> <span class="wb-status-pill {{ $message->notificationClass() }}">{{ $message->notificationLabel() }}</span></div>
                <div><strong>{{ $adminText('recipient_label') }}</strong> {{ $message->notification_recipient ?? '-' }}</div>
                <div><strong>{{ $adminText('recipient_source_label') }}</strong> {{ $message->notificationSourceLabel() }}</div>
                <div><strong>{{ $adminText('attempted_sent_at_label') }}</strong> {{ $message->notification_sent_at?->format('Y-m-d H:i:s') ?? '-' }}</div>
                <div><strong>{{ $adminText('failure_or_skipped_reason_label') }}</strong> {{ $message->notificationDetail() ?? '-' }}</div>
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
            <div><strong>{{ $adminText('editorial_status_label') }}</strong> <span class="wb-status-pill {{ $message->statusClass() }}">{{ $message->status }}</span></div>
            <div><strong>{{ $adminText('spam_score_label') }}</strong> {{ $message->spam_score ?? 0 }}</div>
            <div><strong>{{ $adminText('spam_signals_label') }}</strong>
                @if ($message->spamReasonLabels() === [])
                    -
                @else
                    {{ implode(', ', $message->spamReasonLabels()) }}
                @endif
            </div>
            <div class="wb-text-sm wb-text-muted">{{ $adminText('classification_help') }}</div>
        </div>
    </div>

    <div class="wb-card wb-card-muted">
        <div class="wb-card-header"><strong>{{ $adminText('technical_details') }}</strong></div>
        <div class="wb-card-body wb-stack wb-gap-2">
            <div class="wb-text-sm wb-text-muted">{{ $adminText('technical_details_help') }}</div>
            <div class="wb-grid wb-grid-2">
                <div><strong>{{ $adminText('ip_address_label') }}</strong> {{ $message->ip_address ?? '-' }}</div>
                <div><strong>{{ $adminText('user_agent_label') }}</strong> {{ $message->user_agent ?? '-' }}</div>
                <div><strong>{{ $adminText('block_id_label') }}</strong> {{ $message->block_id ? '#'.$message->block_id : '-' }}</div>
                <div><strong>{{ $adminText('page_id_label') }}</strong> {{ $message->page_id ? '#'.$message->page_id : '-' }}</div>
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
