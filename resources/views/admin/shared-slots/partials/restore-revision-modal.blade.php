@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key, array $replace = []) => $adminTranslator->get('admin.shared_slots.'.$key, $adminLocale, $replace);
    $modalId = 'restore-shared-slot-revision-'.$revision->id;
@endphp

@component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
    'id' => $modalId,
    'title' => $adminText('restore_title'),
    'description' => $adminText('restore_description'),
    'action' => route('admin.shared-slots.revisions.restore', [$sharedSlot, $revision]),
    'method' => 'POST',
    'submitLabel' => $adminText('restore_revision'),
])
    <p>{{ $adminText('restore_confirm_prefix') }} <strong>{{ $adminText('revision_title', ['id' => $revision->id]) }}</strong> {{ $adminText('restore_confirm_infix') }} <strong>{{ $sharedSlot->name }}</strong>?</p>

    <dl class="wb-stack wb-gap-2 wb-text-sm">
        <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
            <dt class="wb-text-muted">{{ $adminText('created') }}</dt>
            <dd>{{ $revision->created_at?->format('Y-m-d H:i') }}</dd>
        </div>
        <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
            <dt class="wb-text-muted">{{ $adminText('event') }}</dt>
            <dd>{{ $revision->eventText() }}</dd>
        </div>
    </dl>

    <div class="wb-alert wb-alert-warning">
        {{ $adminText('restore_warning') }}
    </div>
@endcomponent
