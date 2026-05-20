@php
    $isReadOnlySite = ($sharedSlot->exists && auth()->user()?->isEditor()) ?? false;
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-card wb-card-muted">
        <div class="wb-card-body">
            <p class="wb-text-sm wb-text-muted">Shared Slots provide reusable slot content for pages. The page shell and slot name still own the public wrapper; this Shared Slot only provides the inner block tree.</p>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="shared_slot_site_id">Site</label>
            <select id="shared_slot_site_id" name="site_id" class="wb-select" @disabled($isReadOnlySite)>
                @foreach ($sites as $site)
                    <option value="{{ $site->id }}" @selected((int) old('site_id', $sharedSlot->site_id) === (int) $site->id)>{{ $site->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="shared_slot_name">Name</label>
            <input id="shared_slot_name" name="name" class="wb-input" type="text" value="{{ old('name', $sharedSlot->name) }}" maxlength="255" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="shared_slot_handle">Handle</label>
            <input id="shared_slot_handle" name="handle" class="wb-input" type="text" value="{{ old('handle', $sharedSlot->handle) }}" maxlength="100" required>
            <div class="wb-text-sm wb-text-muted">Slug-like key, unique per site.</div>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="shared_slot_slot_name">Slot</label>
            <input id="shared_slot_slot_name" name="slot_name" class="wb-input" type="text" list="shared-slot-name-options" value="{{ old('slot_name', $sharedSlot->slot_name) }}" maxlength="100">
            <datalist id="shared-slot-name-options">
                @foreach (\WebBlocks\Cms\Models\SharedSlot::COMMON_SLOT_NAMES as $slotName)
                    <option value="{{ $slotName }}"></option>
                @endforeach
            </datalist>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="shared_slot_public_shell">Page Layout</label>
            <select id="shared_slot_public_shell" name="public_shell" class="wb-select">
                <option value="">Any Page Layout</option>
                @foreach (app(\WebBlocks\Cms\Support\Pages\PageLayoutManager::class)->sharedSlotSelectionOptions($sharedSlot->public_shell) as $layoutOption)
                    <option value="{{ $layoutOption['value'] }}" @selected(old('public_shell', $sharedSlot->public_shell) === $layoutOption['value'])>{{ $layoutOption['label'] }}</option>
                @endforeach
            </select>
            <div class="wb-text-sm wb-text-muted">Optional compatibility constraint. Leave empty to keep this Shared Slot generic across any Page Layout. Select a Page Layout to restrict it to pages using that exact layout handle stored on <code>public_shell</code>.</div>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="shared_slot_is_active">Status</label>
            <select id="shared_slot_is_active" name="is_active" class="wb-select">
                <option value="1" @selected((bool) old('is_active', $sharedSlot->is_active ?? true))>Active</option>
                <option value="0" @selected(! (bool) old('is_active', $sharedSlot->is_active ?? true))>Inactive</option>
            </select>
        </div>
    </div>
</div>
