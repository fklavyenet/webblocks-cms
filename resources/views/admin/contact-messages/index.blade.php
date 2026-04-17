@extends('layouts.admin', ['title' => 'Contact Messages', 'heading' => 'Contact Messages'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Contact Messages',
        'description' => 'Review saved public enquiries, check notification delivery, and update editorial status.',
        'count' => $messages->total(),
    ])

    @include('admin.partials.flash')

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
                                <th>Source</th>
                                <th>Status</th>
                                <th>Notification</th>
                                <th>Received</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($messages as $message)
                                <tr>
                                    <td>{{ $message->name }}</td>
                                    <td><a href="mailto:{{ $message->email }}" class="wb-link">{{ $message->email }}</a></td>
                                    <td>{{ $message->subject ?: '-' }}</td>
                                    <td>
                                        <div class="wb-stack wb-gap-1">
                                            <span>{{ $message->page?->title ?? '-' }}</span>
                                            @if ($message->source_url)
                                                <a href="{{ $message->source_url }}" target="_blank" rel="noopener noreferrer" class="wb-text-sm wb-link">Open source</a>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        <span class="wb-status-pill {{ $message->statusClass() }}">{{ $message->status }}</span>
                                    </td>
                                    <td>
                                        <span class="wb-status-pill {{ $message->notificationClass() }}">{{ $message->notificationLabel() }}</span>
                                    </td>
                                    <td>{{ $message->created_at?->format('Y-m-d H:i') }}</td>
                                    <td>
                                        <div class="wb-cluster wb-cluster-2 wb-row-end">
                                            <a href="{{ route('admin.contact-messages.show', $message) }}" class="wb-action-btn wb-action-btn-view" title="Open message" aria-label="Open message">
                                                <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                                            </a>

                                            <form method="POST" action="{{ route('admin.contact-messages.status', $message) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="read">
                                                <button type="submit" class="wb-action-btn wb-action-btn-edit" title="Mark as read" aria-label="Mark as read">
                                                    <i class="wb-icon wb-icon-mail-opened" aria-hidden="true"></i>
                                                </button>
                                            </form>

                                            <form method="POST" action="{{ route('admin.contact-messages.status', $message) }}">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="status" value="archived">
                                                <button type="submit" class="wb-action-btn" title="Archive message" aria-label="Archive message">
                                                    <i class="wb-icon wb-icon-archive" aria-hidden="true"></i>
                                                </button>
                                            </form>
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
