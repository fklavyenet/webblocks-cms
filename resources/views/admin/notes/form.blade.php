@php
    $id = $record ? 'notes-edit' : 'notes-add';
    $title = $text($record ? 'edit' : 'add');
    $noteErrors = $errors->getBag('note');
    $restore = ($record !== null || $editing === null) && $noteErrors->any();
    $open = $record || $restore;
    $target = $record?->is_private ? ($record->recipient_id ?? 'author') : 'everyone';
    $keepRecipient = $record?->recipient_id && !$recipients->contains('id', $record->recipient_id);
    if ($keepRecipient) {
        $target = 'keep';
    }
    $input = function ($key, $fallback) use ($restore) {
        $value = $restore ? old($key, $fallback) : $fallback;
        return is_scalar($value) ? (string) $value : '';
    };
    $selected = $input('audience_target', $target);
@endphp

<div class="wb-modal wb-modal-lg" id="{{ $id }}" role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-heading" @if ($open) data-wb-admin-autoload-overlay @endif @if ($record) data-wb-admin-close-url="{{ $indexUrl }}" @endif hidden>
    <div class="wb-modal-dialog">
        <div class="wb-modal-header">
            <h2 class="wb-modal-title" id="{{ $id }}-heading">{{ $title }}</h2>
            <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ __('webblocks-cms::admin.common.close_named', ['name' => $title]) }}">
                <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
            </button>
        </div>
        <form method="POST" action="{{ $record ? route('admin.notes.update', ['note' => $record->id] + $filters) : route('admin.notes.store', $filters) }}" data-wb-modal-submit-label="saving">
            @csrf
            @if ($record)
                @method('PATCH')
            @endif
            <div class="wb-modal-body wb-stack wb-gap-3">
                @if ($restore)
                    <div class="wb-alert wb-alert-danger" role="alert">{{ $noteErrors->first() }}</div>
                @endif
                <div class="wb-field">
                    <label class="wb-label" for="{{ $id }}-title">{{ $text('subject') }}</label>
                    <input class="wb-input" id="{{ $id }}-title" name="title" value="{{ $input('title', $record?->title) }}" required maxlength="180" @if ($restore && $noteErrors->has('title')) aria-invalid="true" aria-describedby="{{ $id }}-title-error" @endif>
                    @if ($restore && $noteErrors->has('title'))
                        <div id="{{ $id }}-title-error" class="wb-field-error">{{ $noteErrors->first('title') }}</div>
                    @endif
                </div>
                <div class="wb-field">
                    <label class="wb-label" for="{{ $id }}-body">{{ $text('body') }}</label>
                    <textarea class="wb-textarea" id="{{ $id }}-body" name="body" rows="8" required maxlength="{{ \WebBlocks\Cms\Http\Requests\NoteRequest::MAX_BODY }}" @if ($restore && $noteErrors->has('body')) aria-invalid="true" aria-describedby="{{ $id }}-body-error" @endif>{{ $input('body', $record?->body) }}</textarea>
                    @if ($restore && $noteErrors->has('body'))
                        <div id="{{ $id }}-body-error" class="wb-field-error">{{ $noteErrors->first('body') }}</div>
                    @endif
                </div>
                <div class="wb-field">
                    <label class="wb-label" for="{{ $id }}-recipient">{{ $text('recipient') }}</label>
                    @php($recipientError = $restore ? ($noteErrors->first('recipient_id') ?: $noteErrors->first('audience_target')) : null)
                    <select class="wb-select" id="{{ $id }}-recipient" name="audience_target" required @if ($recipientError) aria-invalid="true" aria-describedby="{{ $id }}-recipient-error" @endif>
                        @if ($recipientError && !$recipients->contains('id', $selected) && !in_array($selected, ['everyone', 'author', 'keep'], true))
                            <option value="" selected disabled>{{ $text('invalid_recipient') }}</option>
                        @endif
                        <option value="everyone" @selected($selected === 'everyone')>{{ $text('everyone') }}</option>
                        <option value="author" @selected($selected === 'author')>{{ $text('author_only') }}</option>
                        @if ($keepRecipient)
                            <option value="keep" @selected($selected === 'keep')>{{ $text('unavailable_account') }}</option>
                        @endif
                        @foreach ($recipients as $recipient)
                            <option value="{{ $recipient->id }}" @selected($selected === (string) $recipient->id)>{{ $recipient->name }}</option>
                        @endforeach
                    </select>
                    @if ($recipientError)
                        <div id="{{ $id }}-recipient-error" class="wb-field-error">{{ $recipientError }}</div>
                    @endif
                </div>
            </div>
            <div class="wb-modal-footer">
                <x-webblocks-cms::admin.form-actions
                    :cancel-url="$record ? $indexUrl : null"
                    :cancel-type="$record ? 'link' : 'button'"
                    :cancel-attributes="['data-wb-dismiss' => 'modal']" />
            </div>
        </form>
    </div>
</div>
