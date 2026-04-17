@extends('layouts.admin', ['title' => 'Contact Message', 'heading' => 'Contact Message'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => $message->subject ?: 'Contact Message',
        'description' => 'Inspect the saved submission record and manage its editorial status.',
        'actions' => '<div class="wb-cluster wb-cluster-2"><span class="wb-status-pill '.$message->statusClass().'">'.e($message->status).'</span><a href="'.route('admin.contact-messages.index').'" class="wb-btn wb-btn-secondary">Back to Inbox</a></div>',
    ])

    @include('admin.partials.flash')

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

        <form method="POST" action="{{ route('admin.contact-messages.destroy', $message) }}" onsubmit="return confirm('Delete this message?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="wb-btn wb-btn-danger">Delete</button>
        </form>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-card">
            <div class="wb-card-header"><strong>Sender</strong></div>
            <div class="wb-card-body wb-stack wb-gap-2">
                <div><strong>Name:</strong> {{ $message->name }}</div>
                <div><strong>Email:</strong> <a href="mailto:{{ $message->email }}" class="wb-link">{{ $message->email }}</a></div>
                <div><strong>Subject:</strong> {{ $message->subject ?? '—' }}</div>
                <div><strong>Received:</strong> {{ $message->created_at?->format('Y-m-d H:i:s') }}</div>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header"><strong>Source</strong></div>
            <div class="wb-card-body wb-stack wb-gap-2">
                <div><strong>Page:</strong> {{ $message->page?->title ?? '-' }}</div>
                <div><strong>Path:</strong> <code>{{ $message->sourcePath() }}</code></div>
                <div><strong>Source URL:</strong> @if ($message->source_url)<a href="{{ $message->source_url }}" target="_blank" rel="noopener noreferrer" class="wb-link">Open source</a>@else - @endif</div>
                <div><strong>Block:</strong> {{ $message->block?->typeName() ?? '-' }}</div>
                <div><strong>Block ID:</strong> {{ $message->block_id ? '#'.$message->block_id : '-' }}</div>
                <div><strong>Slot:</strong> {{ $message->block?->slotType?->name ?? $message->block?->slotName() ?? '-' }}</div>
                <div><strong>Referer:</strong> {{ $message->referer ?? '-' }}</div>
            </div>
        </div>
    </div>

    <div class="wb-card">
        <div class="wb-card-header"><strong>Message</strong></div>
        <div class="wb-card-body wb-stack wb-gap-2" style="white-space: pre-line; min-height: 12rem;">{{ $message->message }}</div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-card">
            <div class="wb-card-header"><strong>Notification</strong></div>
            <div class="wb-card-body wb-stack wb-gap-2">
                <div><strong>Notification:</strong> <span class="wb-status-pill {{ $message->notificationClass() }}">{{ $message->notificationLabel() }}</span></div>
                <div><strong>Recipient:</strong> {{ $message->notification_recipient ?? '-' }}</div>
                <div><strong>Sent At:</strong> {{ $message->notification_sent_at?->format('Y-m-d H:i:s') ?? '-' }}</div>
                <div><strong>Error:</strong> {{ $message->notification_error ?? '-' }}</div>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header"><strong>Request Metadata</strong></div>
            <div class="wb-card-body wb-stack wb-gap-2">
                <div><strong>IP Address:</strong> {{ $message->ip_address ?? '-' }}</div>
                <div><strong>User Agent:</strong> {{ $message->user_agent ?? '-' }}</div>
            </div>
        </div>
    </div>
@endsection
