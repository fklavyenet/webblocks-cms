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
            @include('admin.partials.listing-filters', [
                'action' => route('admin.contact-messages.index'),
                'search' => [
                    'id' => 'contact_messages_search',
                    'name' => 'search',
                    'label' => 'Search',
                    'value' => $filters['search'] ?? '',
                    'placeholder' => 'Search name, email, subject, or message',
                ],
                'selects' => [
                    [
                        'id' => 'contact_messages_status',
                        'name' => 'status',
                        'label' => 'Status',
                        'selected' => $filters['status'] ?? '',
                        'placeholder' => 'All statuses',
                        'options' => collect(\App\Models\ContactMessage::statuses())
                            ->mapWithKeys(fn (string $status): array => [$status => ucfirst($status)])
                            ->all(),
                    ],
                    [
                        'id' => 'contact_messages_notification',
                        'name' => 'notification',
                        'label' => 'Notification',
                        'selected' => $filters['notification'] ?? '',
                        'placeholder' => 'All notifications',
                        'options' => [
                            'sent' => 'Sent',
                            'pending' => 'Pending',
                            'failed' => 'Failed',
                            'disabled' => 'Disabled',
                        ],
                    ],
                ],
                'showReset' => ($filters['search'] ?? '') !== '' || ($filters['status'] ?? '') !== '' || ($filters['notification'] ?? '') !== '',
                'resetUrl' => route('admin.contact-messages.index'),
                'applyLabel' => 'Apply',
            ])
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

            @include('admin.partials.pagination', ['paginator' => $messages, 'ariaLabel' => 'Contact messages pagination', 'compact' => true])
        </div>
    @endif
@endsection
