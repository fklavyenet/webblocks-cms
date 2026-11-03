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

    <div class="wb-table-wrap"><table class="wb-table"><tbody>

            <tr><th scope="row">{{ $adminText('handle') }}</th>
            <td><code>{{ $sharedSlot->handle }}</code></td></tr>


            <tr><th scope="row">{{ $adminText('slot') }}</th>
            <td>{{ $sharedSlot->slotLabel() }}</td></tr>


            <tr><th scope="row">{{ $adminText('page_layout') }}</th>
            <td>{{ $sharedSlot->publicShellLabel() }}</td></tr>


            <tr><th scope="row">{{ $adminText('site') }}</th>
            <td>{{ $sharedSlot->site?->name }}</td></tr>

    </tbody></table></div>

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
