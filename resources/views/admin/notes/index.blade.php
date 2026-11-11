@php
    $text = fn (string $key, array $replace = []) => __('webblocks-cms::notes.'.$key, $replace);
    $indexUrl = route('admin.notes.index', $filters);
    $closeFocus = match (true) {
        $editing && ($permissions[$editing->id]['update'] ?? false) => 'notes-edit-trigger-'.$editing->id,
        $reading && ($permissions[$reading->id]['view'] ?? false) => 'notes-read-trigger-'.$reading->id,
        default => 'notes-add-trigger',
    };
    $closeUrl = $indexUrl.'#'.$closeFocus;
    $audiences = ['general' => $text('general'), 'private' => $text('private')];
    if ($administer) {
        $audiences['administration'] = $text('administration');
    }
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $text('title'), 'heading' => $text('title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', ['title' => $text('title'), 'description' => $text('description'), 'count' => $notes->total()])
    @include('webblocks-cms::admin.notes.feedback')
    @if ($errors->getBag('notesBulk')->any())
        <div class="wb-alert wb-alert-danger" role="alert">{{ $errors->getBag('notesBulk')->first() }}</div>
    @endif

    <div class="wb-card wb-card-muted">
        <div class="wb-card-body">
            @include('webblocks-cms::admin.partials.listing-filters', [
                'action' => route('admin.notes.index'),
                'search' => ['id' => 'notes-search', 'name' => 'search', 'label' => $text('search'), 'value' => $filters['search'] ?? ''],
                'selects' => [
                    ['id' => 'notes-audience', 'name' => 'audience', 'label' => $text('audience'), 'selected' => $filters['audience'] ?? '', 'placeholder' => $text('any_audience'), 'options' => $audiences],
                    ['id' => 'notes-status', 'name' => 'status', 'label' => $text('status'), 'selected' => $filters['status'] ?? '', 'placeholder' => $text('any_status'), 'options' => ['read' => $text('read'), 'unread' => $text('unread')]],
                ],
                'showReset' => !empty(array_filter($filters)),
                'resetUrl' => route('admin.notes.index'),
                'applyLabel' => $text('apply'),
                'resetLabel' => $text('clear'),
            ])
        </div>
    </div>

    <div class="wb-card" @unless ($administration) data-wb-admin-bulk-listing @endunless>
        <div class="wb-card-header wb-cluster wb-cluster-between wb-flex-wrap">
            <div class="wb-cluster wb-cluster-2">
                <strong>{{ $text($administration ? 'administration' : 'title') }}</strong>
                <span class="wb-status-pill wb-status-info">{{ $notes->total() }}</span>
            </div>
            <button type="button" id="notes-add-trigger" class="wb-btn wb-btn-primary" data-wb-toggle="modal" data-wb-target="#notes-add" aria-haspopup="dialog">
                <i class="wb-icon wb-icon-plus" aria-hidden="true"></i> {{ $text('add') }}
            </button>
        </div>
        <div class="wb-card-body wb-stack wb-gap-3">
            @if ($administration)
                <p class="wb-text-sm wb-text-muted">{{ $text('administration_help') }}</p>
            @endif
            @if ($notes->isEmpty())
                <div class="wb-empty"><div class="wb-empty-title">{{ $text('empty') }}</div></div>
            @else
                @unless ($administration)
                    @component('webblocks-cms::admin.partials.listing-bulk-actions', ['label' => $text('selected')])
                        @slot('additionalActions')
                            <form id="bulk-note-read-state" method="POST" action="{{ route('admin.notes.bulk-read-state', $filters) }}" class="wb-cluster wb-cluster-2 wb-flex-wrap">
                                @csrf
                                @method('PATCH')
                                <button type="submit" name="read" value="1" class="wb-btn wb-btn-secondary" data-wb-admin-bulk-requires-status="unread" disabled>{{ $text('mark_read_selected') }}</button>
                                <button type="submit" name="read" value="0" class="wb-btn wb-btn-secondary" data-wb-admin-bulk-requires-status="read" disabled>{{ $text('mark_unread_selected') }}</button>
                            </form>
                        @endslot
                    @endcomponent
                @endunless
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover">
                        <thead>
                            <tr>
                                @unless ($administration)
                                    <th scope="col">
                                        <label class="wb-check" for="select-all-visible-notes">
                                            <input id="select-all-visible-notes" type="checkbox" aria-label="{{ $text('select_all') }}" data-wb-admin-select-all-visible>
                                        </label>
                                    </th>
                                @endunless
                                <th scope="col">{{ $text('subject') }}</th>
                                @unless ($administration)
                                    <th scope="col">{{ $text('author') }}</th>
                                    <th scope="col">{{ $text('recipient') }}</th>
                                    <th scope="col">{{ $text('status') }}</th>
                                    <th scope="col">{{ $text('created') }}</th>
                                @endunless
                                <th scope="col">{{ $text('actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($notes as $note)
                                @php
                                    $row = ['note' => $note->id] + $filters;
                                    $label = $administration ? $text('edit_only', ['id' => $note->id]) : $note->title;
                                @endphp
                                <tr>
                                    @unless ($administration)
                                        <td>
                                            <label class="wb-check" for="select-note-{{ $note->id }}">
                                                <input id="select-note-{{ $note->id }}" type="checkbox" name="note_ids[]" value="{{ $note->id }}" form="bulk-note-read-state"
                                                    aria-label="{{ $text('select_note', ['title' => $label]) }}" data-wb-admin-row-select
                                                    data-wb-admin-bulk-editable-status="{{ $note->is_read ? 'read' : 'unread' }}">
                                            </label>
                                        </td>
                                    @endunless
                                    <td class="wb-cms-note-title"><strong>{{ $label }}</strong></td>
                                    @unless ($administration)
                                        <td>{{ $note->author?->name ?? $text('unavailable_account') }}</td>
                                        <td>{{ $note->is_private ? ($note->recipient?->name ?? $text('author_only')) : $text('everyone') }}</td>
                                        <td><span class="wb-status-pill {{ $note->is_read ? 'wb-status-neutral' : 'wb-status-info' }}">{{ $text($note->is_read ? 'read' : 'unread') }}</span></td>
                                        <td><time datetime="{{ $note->created_at->toIso8601String() }}">{{ $note->created_at->format('Y-m-d H:i') }}</time></td>
                                    @endunless
                                    <td class="wb-table-actions">
                                        <div class="wb-action-group">
                                            @if ($permissions[$note->id]['view'])
                                                <form method="POST" action="{{ route('admin.notes.open', $row) }}">
                                                    @csrf
                                                    <button type="submit" id="notes-read-trigger-{{ $note->id }}" class="wb-action-btn wb-action-btn-view" aria-haspopup="dialog" title="{{ $text('read_note') }}" aria-label="{{ $text('read_note') }} — {{ $label }}">
                                                        <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            @if ($permissions[$note->id]['update'])
                                                <a href="{{ route('admin.notes.edit', $row) }}" id="notes-edit-trigger-{{ $note->id }}" class="wb-action-btn" aria-haspopup="dialog" title="{{ $text('edit') }}" aria-label="{{ $text('edit') }} — {{ $label }}">
                                                    <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                                                </a>
                                            @endif
                                            @if ($permissions[$note->id]['delete'])
                                                <button type="button" class="wb-action-btn wb-action-btn-delete" data-wb-toggle="modal" data-wb-target="#note-delete-{{ $note->id }}" aria-haspopup="dialog" title="{{ $text('delete') }}" aria-label="{{ $text('delete') }} — {{ $label }}">
                                                    <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @include('webblocks-cms::admin.partials.pagination', ['paginator' => $notes, 'ariaLabel' => $text('title'), 'compact' => true])
            @endif
        </div>
    </div>

    @push('overlays')
        @include('webblocks-cms::admin.notes.form', ['record' => null])
        @if ($editing)
            @include('webblocks-cms::admin.notes.form', ['record' => $editing])
        @elseif ($reading)
            @include('webblocks-cms::admin.notes.form', ['record' => $reading, 'readOnly' => true])
        @endif

        @foreach ($notes as $note)
            @if ($permissions[$note->id]['delete'])
                @component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
                    'id' => 'note-delete-'.$note->id,
                    'nativeDialog' => true,
                    'title' => $text('delete'),
                    'description' => $text('delete_prompt', ['title' => $note->title]),
                    'action' => route('admin.notes.destroy', ['note' => $note->id] + $filters),
                ])
                @endcomponent
            @endif
        @endforeach
    @endpush

    @unless ($administration)
        @push('admin-scripts')
            @php($bulkActionsJsPath = public_path('cms/js/admin/listing-bulk-actions.js'))
            @if (is_file($bulkActionsJsPath))
                <script src="{{ asset('cms/js/admin/listing-bulk-actions.js') }}?v={{ filemtime($bulkActionsJsPath) }}" defer></script>
            @endif
        @endpush
    @endunless
@endsection
