@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key, array $replace = []) => $adminTranslator->get('admin.shared_slots.'.$key, $adminLocale, $replace);
    $referenceCount = (int) ($referenceCount ?? 0);
    $modalId = 'delete-shared-slot-'.$sharedSlot->id;
@endphp

@component('webblocks-cms::admin.partials.destructive-confirmation-modal', [
    'id' => $modalId,
    'title' => $adminText('delete_title'),
    'description' => $adminText('delete_description'),
    'action' => route('admin.shared-slots.destroy', $sharedSlot),
    'method' => 'DELETE',
    'submitLabel' => $adminText('delete_shared_slot'),
    // The server rejects a delete while any page slot still points at this Shared
    // Slot, so the modal says so up front instead of letting the submit fail.
    'submitAttributes' => $referenceCount > 0 ? ['disabled' => true] : [],
])
    <p>{{ $adminText('delete_confirm_prefix') }} <strong>{{ $sharedSlot->name }}</strong>? {{ $adminText('cannot_be_undone') }}</p>

    <dl class="wb-stack wb-gap-2 wb-text-sm">
        <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
            <dt class="wb-text-muted">{{ $adminText('handle') }}</dt>
            <dd><code>{{ $sharedSlot->handle }}</code></dd>
        </div>
        <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
            <dt class="wb-text-muted">{{ $adminText('slot') }}</dt>
            <dd>{{ $sharedSlot->slotLabel() }}</dd>
        </div>
        <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
            <dt class="wb-text-muted">{{ $adminText('page_layout') }}</dt>
            <dd>{{ $sharedSlot->publicShellLabel() }}</dd>
        </div>
        <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
            <dt class="wb-text-muted">{{ $adminText('site') }}</dt>
            <dd>{{ $sharedSlot->site?->name }}</dd>
        </div>
    </dl>

    @if ($referenceCount > 0)
        <div class="wb-alert wb-alert-danger">
            {{ $adminText('delete_blocked_warning', ['count' => $referenceCount]) }}
        </div>
    @else
        <div class="wb-alert wb-alert-warning">
            {{ $adminText('delete_blocks_warning') }}
        </div>
    @endif
@endcomponent
