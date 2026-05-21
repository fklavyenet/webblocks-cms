@extends('webblocks-cms::layouts.admin', ['title' => 'Contact Messages', 'heading' => 'Contact Messages'])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'Contact Messages',
        'description' => 'Review saved public enquiries, check notification delivery, and update editorial status.',
        'count' => $totalCount,
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-body">
            @include('webblocks-cms::admin.partials.listing-filters', [
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
                        'options' => collect(\WebBlocks\Cms\Models\ContactMessage::statuses())
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
        <div class="wb-card" data-wb-admin-bulk-listing>
            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <strong>Contact Messages</strong>
                    <span class="wb-status-pill wb-status-info" data-admin-list-count>{{ $filteredCount }}</span>
                </div>
            </div>
            <div class="wb-card-body">
                @include('webblocks-cms::admin.partials.listing-bulk-actions', [
                    'label' => 'selected',
                    'deleteTarget' => '#bulk-delete-contact-messages-modal',
                    'deleteLabel' => 'Delete selected',
                ])

                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover">
                        <thead>
                            <tr>
                                <th>
                                    <label class="wb-checkbox" for="select_all_visible_contact_messages">
                                        <input id="select_all_visible_contact_messages" type="checkbox" data-wb-admin-select-all-visible aria-label="Select all visible contact messages">
                                        <span class="wb-sr-only">Select all visible contact messages</span>
                                    </label>
                                </th>
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
                                    <td>
                                        <label class="wb-checkbox" for="contact_message_select_{{ $message->id }}">
                                            <input id="contact_message_select_{{ $message->id }}" type="checkbox" value="{{ $message->id }}" data-wb-admin-row-select aria-label="Select message from {{ $message->name }}">
                                            <span class="wb-sr-only">Select message from {{ $message->name }}</span>
                                        </label>
                                    </td>
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
                                        @if ($message->notification_error)
                                            <div class="wb-text-sm wb-text-muted">{{ \Illuminate\Support\Str::limit($message->notification_error, 80) }}</div>
                                        @endif
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

            @include('webblocks-cms::admin.partials.pagination', ['paginator' => $messages, 'ariaLabel' => 'Contact messages pagination', 'compact' => true])
        </div>

        @push('overlays')
            @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
                'id' => 'bulk-delete-contact-messages-modal',
                'title' => 'Delete Selected Contact Messages',
                'description' => 'This deletes the selected saved contact submissions.',
                'action' => route('admin.contact-messages.bulk-destroy'),
                'method' => 'DELETE',
                'submitLabel' => 'Delete selected',
                'formAttributes' => [
                    'data-wb-admin-bulk-delete-form' => true,
                    'data-wb-admin-bulk-input-name' => 'contact_message_ids[]',
                ],
                'submitAttributes' => [
                    'data-wb-admin-bulk-delete-submit' => true,
                    'disabled' => true,
                ],
            ])
                <div class="wb-card wb-card-muted">
                    <div class="wb-card-body wb-stack wb-gap-2">
                        <strong><span data-wb-admin-bulk-modal-count>0</span> selected contact messages will be deleted.</strong>
                        <p class="wb-text-sm wb-text-muted">This bulk action applies only to messages visible on this page. The server re-checks access for every selected message before deletion.</p>
                    </div>
                </div>

                <div data-wb-admin-bulk-inputs></div>
                <input type="hidden" name="contact_message_ids[]" value="" disabled data-wb-admin-bulk-empty-input>
            @endcomponent
        @endpush

        @push('scripts')
            @php($bulkActionsJsPath = public_path('cms/js/admin/listing-bulk-actions.js'))
            @if (is_file($bulkActionsJsPath))
                <script src="{{ asset('cms/js/admin/listing-bulk-actions.js') }}?v={{ filemtime($bulkActionsJsPath) }}" defer></script>
            @endif
        @endpush
    @endif
@endsection
