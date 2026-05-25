@extends('webblocks-cms::layouts.admin', ['title' => 'Contact Message', 'heading' => 'Contact Message'])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $message->subject ?: 'Contact Message',
        'description' => 'Inspect the saved submission record and manage its editorial status.',
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
        <div class="wb-card-body wb-stack wb-gap-3">
            <div><strong>Name:</strong> {{ $message->name }}</div>
            <div><strong>Email:</strong> <a href="mailto:{{ $message->email }}" class="wb-link">{{ $message->email }}</a></div>
            <div><strong>Subject:</strong> {{ $message->subject ?? '—' }}</div>
            <div class="wb-stack wb-gap-2">
                <strong>Message:</strong>
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
            <div class="wb-card-header"><strong>Notification</strong></div>
            <div class="wb-card-body wb-stack wb-gap-2">
                <div><strong>Status:</strong> <span class="wb-status-pill {{ $message->notificationClass() }}">{{ $message->notificationLabel() }}</span></div>
                <div><strong>Recipient:</strong> {{ $message->notification_recipient ?? '-' }}</div>
                <div><strong>Sent at:</strong> {{ $message->notification_sent_at?->format('Y-m-d H:i:s') ?? '-' }}</div>
                <div><strong>Failure detail:</strong> {{ $message->notification_error ?? '-' }}</div>
            </div>
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
