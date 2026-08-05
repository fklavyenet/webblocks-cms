@php
    use WebBlocks\Cms\Models\ContactMessage;
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $adminLocale = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('contact_messages_index.'.$key, $adminLocale, $replace);
    $statusLabel = static fn (string $status) => $adminText('status_'.$status);
    $hasActiveFilters = ($filters['search'] ?? '') !== ''
        || ($filters['status'] ?? '') !== ''
        || ($filters['notification'] ?? '') !== '';
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $adminText('title'), 'heading' => $adminText('title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $adminText('title'),
        'description' => $adminText('description'),
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
                    'label' => $adminText('search'),
                    'value' => $filters['search'] ?? '',
                    'placeholder' => $adminText('search_placeholder'),
                ],
                'selects' => [
                    [
                        'id' => 'contact_messages_status',
                        'name' => 'status',
                        'label' => $adminText('status'),
                        'selected' => $filters['status'] ?? '',
                        'placeholder' => $adminText('all_statuses'),
                        'options' => collect(ContactMessage::statuses())
                            ->mapWithKeys(fn (string $status): array => [$status => $statusLabel($status)])
                            ->all(),
                    ],
                    [
                        'id' => 'contact_messages_notification',
                        'name' => 'notification',
                        'label' => $adminText('notification'),
                        'selected' => $filters['notification'] ?? '',
                        'placeholder' => $adminText('all_notifications'),
                        'options' => [
                            'sent' => $adminText('notification_sent'),
                            'failed' => $adminText('notification_failed'),
                            'skipped' => $adminText('notification_skipped'),
                            'not_configured' => $adminText('notification_not_configured'),
                            'pending' => $adminText('notification_pending'),
                        ],
                    ],
                ],
                'showReset' => $hasActiveFilters,
                'resetUrl' => route('admin.contact-messages.index'),
                'applyLabel' => $adminText('apply'),
                'resetLabel' => $adminText('clear_filters'),
            ])
        </div>
    </div>

    @if ($messages->isEmpty())
        <div class="wb-card">
            <div class="wb-card-body">
                <div class="wb-empty">
                    <div class="wb-empty-title">
                        {{ $hasActiveFilters ? $adminText('no_messages_found') : $adminText('no_messages_yet') }}
                    </div>
                    <div class="wb-empty-text">
                        {{ $hasActiveFilters ? $adminText('no_messages_filtered_help') : $adminText('no_messages_help') }}
                    </div>
                    @if ($hasActiveFilters)
                        <div class="wb-empty-action">
                            <a href="{{ route('admin.contact-messages.index') }}" class="wb-btn wb-btn-secondary">{{ $adminText('clear_filters') }}</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @else
        <div class="wb-card" data-wb-admin-bulk-listing>
            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <strong>{{ $adminText('title') }}</strong>
                    <span class="wb-status-pill wb-status-info" data-admin-list-count>{{ $filteredCount }}</span>
                </div>
            </div>
            <div class="wb-card-body">
                @include('webblocks-cms::admin.partials.listing-bulk-actions', [
                    'label' => $adminText('selected'),
                    'deleteTarget' => '#bulk-delete-contact-messages-modal',
                    'deleteLabel' => $adminText('delete_selected'),
                ])

                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover">
                        <thead>
                            <tr>
                                <th>
                                    <label class="wb-check" for="select_all_visible_contact_messages">
                                        <input id="select_all_visible_contact_messages" type="checkbox" data-wb-admin-select-all-visible aria-label="{{ $adminText('select_all_visible') }}">
                                        <span class="wb-sr-only">{{ $adminText('select_all_visible') }}</span>
                                    </label>
                                </th>
                                <th>{{ $adminText('name') }}</th>
                                <th>{{ $adminText('email') }}</th>
                                <th>{{ $adminText('subject') }}</th>
                                <th>{{ $adminText('editorial_status') }}</th>
                                <th>{{ $adminText('email_notification') }}</th>
                                <th>{{ $adminText('received') }}</th>
                                <th>{{ $adminText('actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($messages as $message)
                                <tr>
                                    <td>
                                        <label class="wb-check" for="contact_message_select_{{ $message->id }}">
                                            <input id="contact_message_select_{{ $message->id }}" type="checkbox" value="{{ $message->id }}" data-wb-admin-row-select aria-label="{{ $adminText('select_message_from', ['name' => $message->name]) }}">
                                            <span class="wb-sr-only">{{ $adminText('select_message_from', ['name' => $message->name]) }}</span>
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
                                        @if ($message->spam_score > 0)
                                            <div class="wb-text-sm wb-text-muted">{{ $adminText('spam_score', ['score' => $message->spam_score]) }}</div>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="wb-action-group">
                                            <span class="wb-status-pill {{ $message->notificationClass() }}">{{ $message->notificationLabel() }}</span>
                                            @if ($message->resolvedNotificationStatus() === 'failed')
                                                @php($notificationTooltip = \Illuminate\Support\Str::limit($message->notificationDetail() ?: $adminText('open_message_notification_details'), 120))
                                                <button type="button" class="wb-action-btn wb-action-btn-view" data-wb-tooltip="{{ $notificationTooltip }}" data-wb-tooltip-placement="top" aria-label="{{ $adminText('notification_failure_summary') }}">
                                                    <i class="wb-icon wb-icon-circle-help" aria-hidden="true"></i>
                                                </button>
                                            @endif
                                        </div>
                                        @if ($message->hasLegacyNotificationState())
                                            <span class="wb-sr-only">{{ $adminText('legacy_notification_state') }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $message->created_at?->format('Y-m-d H:i') }}</td>
                                    <td class="wb-table-actions">
                                        <div class="wb-action-group">
                                            <a href="{{ route('admin.contact-messages.show', $message) }}" class="wb-action-btn wb-action-btn-view" title="{{ $adminText('view_message') }}" aria-label="{{ $adminText('view_message') }}">
                                                <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                                            </a>

                                            <button type="button" class="wb-action-btn wb-action-btn-delete" data-wb-toggle="modal" data-wb-target="#delete-contact-message-modal-{{ $message->id }}" title="{{ $adminText('delete_message') }}" aria-label="{{ $adminText('delete_message') }}" aria-haspopup="dialog">
                                                <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @include('webblocks-cms::admin.partials.pagination', ['paginator' => $messages, 'ariaLabel' => $adminText('pagination'), 'compact' => true])
        </div>

        @push('overlays')
            @foreach ($messages as $message)
                @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
                    'id' => 'delete-contact-message-modal-'.$message->id,
                    'title' => $adminText('delete_contact_message'),
                    'description' => $adminText('delete_contact_message_description'),
                    'action' => route('admin.contact-messages.destroy', $message),
                    'method' => 'DELETE',
                    'submitLabel' => $adminText('delete_message'),
                ])
                    <div class="wb-card wb-card-muted">
                        <div class="wb-card-body wb-stack wb-gap-2">
                            <div><strong>{{ $message->detailTitleName() }}</strong></div>
                            <div class="wb-text-sm wb-text-muted">{{ $adminText('from_sender', ['name' => $message->name ?: '-', 'email' => $message->email ?: '-']) }}</div>
                        </div>
                    </div>

                    <input type="hidden" name="return_url" value="{{ $currentReturnUrl }}">
                    <p class="wb-text-sm wb-text-muted">{{ $adminText('cannot_be_undone') }}</p>
                @endcomponent
            @endforeach

            @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
                'id' => 'bulk-delete-contact-messages-modal',
                'title' => $adminText('delete_selected_contact_messages'),
                'description' => $adminText('delete_selected_description'),
                'action' => route('admin.contact-messages.bulk-destroy'),
                'method' => 'DELETE',
                'submitLabel' => $adminText('delete_selected'),
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
                        <strong>{!! $adminText('bulk_delete_count_html') !!}</strong>
                        <p class="wb-text-sm wb-text-muted">{{ $adminText('bulk_delete_help') }}</p>
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
