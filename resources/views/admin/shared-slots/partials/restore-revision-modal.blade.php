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
    'pendingOperation' => 'restoring',
    'action' => route('admin.shared-slots.revisions.restore', [$sharedSlot, $revision]),
    'method' => 'POST',
    'submitLabel' => $adminText('restore_revision'),
])
    <p>{{ $adminText('restore_confirm_prefix') }} <strong>{{ $adminText('revision_title', ['id' => $revision->id]) }}</strong> {{ $adminText('restore_confirm_infix') }} <strong>{{ $sharedSlot->name }}</strong>?</p>

    <div class="wb-table-wrap"><table class="wb-table"><tbody>

            <tr><th scope="row">{{ $adminText('created') }}</th>
            <td>{{ $revision->created_at?->format('Y-m-d H:i') }}</td></tr>


            <tr><th scope="row">{{ $adminText('event') }}</th>
            <td>{{ $revision->eventText() }}</td></tr>

    </tbody></table></div>

    <div class="wb-alert wb-alert-warning">
        {{ $adminText('restore_warning') }}
    </div>
@endcomponent
