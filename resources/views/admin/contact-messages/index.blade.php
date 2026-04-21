@extends('layouts.admin', ['title' => 'Contact Messages', 'heading' => 'Contact Messages'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Contact Messages',
        'description' => 'Review saved public enquiries, check notification delivery, and update editorial status.',
        'count' => $messages->total(),
    ])

    @include('admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-body">
            <form method="GET" action="{{ route('admin.contact-messages.index') }}" class="wb-cluster wb-cluster-2 wb-cluster-between">
                <div class="wb-cluster wb-cluster-2">
                    <div class="wb-stack wb-gap-1">
                        <label for="contact_messages_search">Search</label>
                        <input id="contact_messages_search" name="search" type="text" class="wb-input" value="{{ $filters['search'] ?? '' }}" placeholder="Search name, email, subject, or message">
                    </div>

                    <div class="wb-stack wb-gap-1">
                        <label for="contact_messages_status">Status</label>
                        <select id="contact_messages_status" name="status" class="wb-select">
                            <option value="">All statuses</option>
                            @foreach (\App\Models\ContactMessage::statuses() as $status)
                                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wb-stack wb-gap-1">
                        <label for="contact_messages_notification">Notification</label>
                        <select id="contact_messages_notification" name="notification" class="wb-select">
                            <option value="">All notifications</option>
                            <option value="sent" @selected(($filters['notification'] ?? '') === 'sent')>Sent</option>
                            <option value="pending" @selected(($filters['notification'] ?? '') === 'pending')>Pending</option>
                            <option value="failed" @selected(($filters['notification'] ?? '') === 'failed')>Failed</option>
                            <option value="disabled" @selected(($filters['notification'] ?? '') === 'disabled')>Disabled</option>
                        </select>
                    </div>
                </div>

                <div class="wb-cluster wb-cluster-2 wb-admin-filter-actions-end">
                    <button type="submit" class="wb-btn wb-btn-primary">Apply</button>
                    @if (($filters['search'] ?? '') !== '' || ($filters['status'] ?? '') !== '' || ($filters['notification'] ?? '') !== '')
                        <a href="{{ route('admin.contact-messages.index') }}" class="wb-btn wb-btn-secondary">Clear</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    @if ($messages->isEmpty())
        <div class="wb-card">
            <div class="wb-card-body">
                <div class="wb-empty">
                    <div class="wb-empty-title">No messages yet</div>
                    <div class="wb-empty-text">Published Contact Form blocks will save new submissions here.</div>
                </div>
            </div>
        </div>
    @else
        <div class="wb-card">
            <div class="wb-card-body">
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Notification</th>
                                <th>Received</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($messages as $message)
                                <tr>
                                    <td class="wb-contact-message-cell">
                                        <strong>{{ $message->name }}</strong>
                                    </td>
                                    <td class="wb-contact-message-cell"><a href="mailto:{{ $message->email }}" class="wb-link">{{ $message->email }}</a></td>
                                    <td class="wb-contact-message-cell">
                                        @if ($message->subject)
                                            {{ $message->subject }}
                                        @else
                                            <span class="wb-text-sm wb-text-muted">&mdash;</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="wb-status-pill {{ $message->statusClass() }}">{{ $message->status }}</span>
                                    </td>
                                    <td>
                                        <span class="wb-status-pill {{ $message->notificationClass() }}">{{ $message->notificationLabel() }}</span>
                                    </td>
                                    <td>{{ $message->created_at?->format('Y-m-d H:i') }}</td>
                                    <td>
                                        <div class="wb-action-group">
                                            <a href="{{ route('admin.contact-messages.show', $message) }}" class="wb-action-btn wb-action-btn-view" title="View message detail" aria-label="View message detail">
                                                <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                                            </a>

                                            <form method="POST" action="{{ route('admin.contact-messages.status', $message) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="{{ $message->status === 'new' ? 'read' : 'new' }}">
                                                <button type="submit" class="wb-action-btn wb-action-btn-edit" title="{{ $message->status === 'new' ? 'Mark as read' : 'Mark as new' }}" aria-label="{{ $message->status === 'new' ? 'Mark as read' : 'Mark as new' }}">
                                                    <i class="wb-icon {{ $message->status === 'new' ? 'wb-icon-mail-opened' : 'wb-icon-mail' }}" aria-hidden="true"></i>
                                                </button>
                                            </form>

                                            <div class="wb-dropdown wb-dropdown-end">
                                                <button class="wb-action-btn" type="button" data-wb-toggle="dropdown" data-wb-target="#contact-message-actions-{{ $message->id }}" aria-expanded="false" title="More message actions" aria-label="More message actions">
                                                    <i class="wb-icon wb-icon-menu" aria-hidden="true"></i>
                                                </button>

                                                <div class="wb-dropdown-menu" id="contact-message-actions-{{ $message->id }}">
                                                    <form method="POST" action="{{ route('admin.contact-messages.status', $message) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="status" value="archived">
                                                        <button type="submit" class="wb-dropdown-item">Archive</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('admin.contact-messages.status', $message) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="status" value="spam">
                                                        <button type="submit" class="wb-dropdown-item">Mark spam</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('admin.contact-messages.status', $message) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <input type="hidden" name="status" value="replied">
                                                        <button type="submit" class="wb-dropdown-item">Mark replied</button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @include('admin.partials.pagination', ['paginator' => $messages])
        </div>
    @endif
@endsection
