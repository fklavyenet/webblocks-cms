@extends('webblocks-cms::layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => 'Inspect the saved submission record, spam signals, and notification delivery without mixing editorial status with SMTP state.',
        'actions' => '<div class="wb-cluster wb-cluster-2"><span class="wb-status-pill '.$message->statusClass().'">'.e($message->status).'</span><a href="'.route('admin.contact-messages.index').'" class="wb-btn wb-btn-secondary">Back to Inbox</a></div>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-cluster wb-cluster-between wb-cluster-2">
        <div class="wb-cluster wb-cluster-2">
            @foreach ($statuses as $status)
                <form method="POST" action="{{ route('admin.contact-messages.status', $message) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="status" value="{{ $status }}">
                    <button type="submit" class="wb-btn {{ $message->status === $status ? 'wb-btn-primary' : 'wb-btn-secondary' }}">{{ $status === 'new' ? 'Mark new' : 'Mark '.$status }}</button>
                </form>
            @endforeach
        </div>

        <button type="button" class="wb-btn wb-btn-danger" data-wb-toggle="modal" data-wb-target="#delete-contact-message-modal">Delete</button>
    </div>

    <div class="wb-card">
        <div class="wb-card-header"><strong>Visitor message</strong></div>
        <div class="wb-card-body wb-stack wb-gap-4">
            <dl class="wb-detail-list wb-contact-message-meta">
                <div class="wb-detail-row">
                    <dt class="wb-detail-label">Name</dt>
                    <dd class="wb-detail-value">{{ $message->name }}</dd>
                </div>
                <div class="wb-detail-row">
                    <dt class="wb-detail-label">Email</dt>
                    <dd class="wb-detail-value"><a href="mailto:{{ $message->email }}" class="wb-link">{{ $message->email }}</a></dd>
                </div>
                <div class="wb-detail-row">
                    <dt class="wb-detail-label">Subject</dt>
                    <dd class="wb-detail-value">{{ $message->subject ?? '—' }}</dd>
                </div>
            </dl>
            <div class="wb-stack wb-gap-2">
                <strong>Message</strong>
                <div class="wb-contact-message-body">{{ $message->message }}</div>
            </div>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-card">
            <div class="wb-card-header"><strong>Submission details</strong></div>
            <div class="wb-card-body wb-stack wb-gap-2">
                <div><strong>Page:</strong> {{ $message->page?->title ?? '-' }}</div>
                <div><strong>Path:</strong> <code>{{ $message->sourcePath() }}</code></div>
                <div><strong>Source URL:</strong> @if ($message->source_url)<a href="{{ $message->source_url }}" target="_blank" rel="noopener noreferrer" class="wb-link">Open source</a>@else - @endif</div>
                <div><strong>Referrer:</strong> {{ $message->referer ?? '-' }}</div>
                <div><strong>Received at:</strong> {{ $message->created_at?->format('Y-m-d H:i:s') }}</div>
                <div><strong>Block / Slot:</strong> {{ $message->block?->typeName() ?? '-' }} / {{ $message->block?->slotType?->name ?? $message->block?->slotName() ?? '-' }}</div>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header"><strong>Email notification</strong></div>
            <div class="wb-card-body wb-stack wb-gap-2">
                <div><strong>Status:</strong> <span class="wb-status-pill {{ $message->notificationClass() }}">{{ $message->notificationLabel() }}</span></div>
                <div><strong>Recipient:</strong> {{ $message->notification_recipient ?? '-' }}</div>
                <div><strong>Recipient source:</strong> {{ $message->notificationSourceLabel() }}</div>
                <div><strong>Attempted/sent at:</strong> {{ $message->notification_sent_at?->format('Y-m-d H:i:s') ?? '-' }}</div>
                <div><strong>Failure or skipped reason:</strong> {{ $message->notificationDetail() ?? '-' }}</div>
                @if ($message->hasLegacyNotificationState())
                    <div class="wb-alert wb-alert-info">This message was saved before explicit notification status metadata was available. Its status is inferred from older notification fields.</div>
                @endif
                <div class="wb-text-sm wb-text-muted">Sent means the CMS handed the message to the configured mail transport. It does not guarantee inbox delivery. Skipped means notification was not attempted, usually because mail or recipient settings are missing or disabled.</div>
                <div class="wb-divider"></div>
                <div class="wb-stack wb-gap-2">
                    <strong>Setup guidance</strong>
                    <ul class="wb-text-sm wb-text-muted">
                        <li>Configure <code>MAIL_*</code> in <code>.env</code>, then run <code>php artisan optimize:clear</code>.</li>
                        <li>Configure Site -&gt; Edit -&gt; Contact recipient when available.</li>
                        <li>Optionally set <code>CONTACT_RECIPIENT_EMAIL</code> for the CMS fallback recipient.</li>
                        <li>Run <code>php artisan contact:mail-diagnose</code>, <code>php artisan contact:mail-diagnose --block={{ $message->block_id ?? 'ID' }}</code>, or <code>php artisan contact:mail-diagnose --send-test=you@example.com</code>.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="wb-card">
        <div class="wb-card-header"><strong>Message classification</strong></div>
        <div class="wb-card-body wb-stack wb-gap-2">
            <div><strong>Editorial status:</strong> <span class="wb-status-pill {{ $message->statusClass() }}">{{ $message->status }}</span></div>
            <div><strong>Spam score:</strong> {{ $message->spam_score ?? 0 }}</div>
            <div><strong>Spam signals:</strong>
                @if ($message->spamReasonLabels() === [])
                    -
                @else
                    {{ implode(', ', $message->spamReasonLabels()) }}
                @endif
            </div>
            <div class="wb-text-sm wb-text-muted">Mark spam stores a durable editorial status for filtering and future spam-signal workflows; it does not change notification delivery history.</div>
        </div>
    </div>

    <div class="wb-card wb-card-muted">
        <div class="wb-card-header"><strong>Technical details</strong></div>
        <div class="wb-card-body wb-stack wb-gap-2">
            <div class="wb-text-sm wb-text-muted">Admin-only request metadata captured with the submission.</div>
            <div class="wb-grid wb-grid-2">
                <div><strong>IP address:</strong> {{ $message->ip_address ?? '-' }}</div>
                <div><strong>User agent:</strong> {{ $message->user_agent ?? '-' }}</div>
                <div><strong>Block ID:</strong> {{ $message->block_id ? '#'.$message->block_id : '-' }}</div>
                <div><strong>Page ID:</strong> {{ $message->page_id ? '#'.$message->page_id : '-' }}</div>
            </div>
        </div>
    </div>
@endsection

@push('overlays')
    @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
        'id' => 'delete-contact-message-modal',
        'title' => 'Delete Contact Message',
        'description' => 'This deletes the saved contact submission.',
        'action' => route('admin.contact-messages.destroy', $message),
        'method' => 'DELETE',
        'submitLabel' => 'Delete message',
    ])
        <div class="wb-card wb-card-muted">
            <div class="wb-card-body wb-stack wb-gap-2">
                <div><strong>{{ $message->subject ?: 'Contact Message #'.$message->id }}</strong></div>
                <div class="wb-text-sm wb-text-muted">From {{ $message->name }} &lt;{{ $message->email }}&gt;</div>
            </div>
        </div>

        <p class="wb-text-sm wb-text-muted">This cannot be undone from the admin UI.</p>
    @endcomponent
@endpush
