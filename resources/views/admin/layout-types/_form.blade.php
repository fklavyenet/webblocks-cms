@php
    $submittedSlots = old('slots');
    $layoutTypeSlots = $submittedSlots
        ? collect($submittedSlots)->map(function (array $slot) use ($slotTypes) {
            $layoutTypeSlot = new \App\Models\LayoutTypeSlot($slot);
            $layoutTypeSlot->slot_type_id = $slot['slot_type_id'] ?? null;
            $layoutTypeSlot->setRelation('slotType', $slotTypes->firstWhere('id', $layoutTypeSlot->slot_type_id));

            return $layoutTypeSlot;
        })
        : ($layoutType->exists ? $layoutType->slots()->with('slotType')->orderBy('sort_order')->get() : collect());
    $selectedSlotTypeIds = $layoutTypeSlots->pluck('slot_type_id')->filter()->values();
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="name">Name</label>
            <input id="name" name="name" class="wb-input" type="text" value="{{ old('name', $layoutType->name) }}" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="slug">Slug</label>
            <input id="slug" name="slug" class="wb-input" type="text" value="{{ old('slug', $layoutType->slug) }}">
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="description">Description</label>
            <textarea id="description" name="description" class="wb-textarea" rows="3">{{ old('description', $layoutType->description) }}</textarea>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="public_shell">Public Shell</label>
            <select id="public_shell" name="public_shell" class="wb-select">
                <option value="default" @selected(old('public_shell', $layoutType->publicShellPreset()) === 'default')>Default</option>
                <option value="docs" @selected(old('public_shell', $layoutType->publicShellPreset()) === 'docs')>Docs</option>
            </select>
        </div>
    </div>

    <div class="wb-grid wb-grid-3">
        <div class="wb-stack wb-gap-1">
            <label for="sort_order">Sort Order</label>
            <input id="sort_order" name="sort_order" class="wb-input" type="number" min="0" value="{{ old('sort_order', $layoutType->sort_order ?? 0) }}" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="status">Status</label>
            <select id="status" name="status" class="wb-select">
                <option value="draft" @selected(old('status', $layoutType->status ?: 'published') === 'draft')>draft</option>
                <option value="published" @selected(old('status', $layoutType->status ?: 'published') === 'published')>published</option>
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="is_system">System</label>
            <select id="is_system" name="is_system" class="wb-select">
                <option value="0" @selected(! old('is_system', $layoutType->is_system))>user</option>
                <option value="1" @selected((bool) old('is_system', $layoutType->is_system))>system</option>
            </select>
        </div>
    </div>

    <div class="wb-card wb-card-accent">
        <div class="wb-card-header">
            <strong>Slots</strong>
        </div>
        <div class="wb-card-body wb-stack wb-gap-4">
            @foreach ($slotTypes as $slotType)
                @php
                    $existingSlot = $layoutTypeSlots->firstWhere('slot_type_id', $slotType->id);
                    $slotIndex = $loop->index;
                    $isEnabled = old("slots.{$slotIndex}.enabled", $existingSlot !== null);
                    $ownership = old("slots.{$slotIndex}.ownership", $existingSlot?->ownership() ?? ($slotType->slug === 'main' ? 'page' : 'layout'));
                    $wrapperPreset = old("slots.{$slotIndex}.wrapper_preset", $existingSlot?->wrapperPreset() ?? match ($slotType->slug) {
                        'header' => 'docs-navbar',
                        'sidebar' => 'docs-sidebar',
                        'main' => 'docs-main',
                        default => 'default',
                    });
                @endphp
                <div class="wb-card wb-card-muted">
                    <div class="wb-card-body wb-grid wb-grid-4">
                        <div class="wb-stack wb-gap-1">
                            <label>
                                <input type="checkbox" name="slots[{{ $slotIndex }}][enabled]" value="1" @checked($isEnabled)>
                                {{ $slotType->name }}
                            </label>
                            <span class="wb-text-sm wb-text-muted"><code>{{ $slotType->slug }}</code></span>
                            <input type="hidden" name="slots[{{ $slotIndex }}][id]" value="{{ old("slots.{$slotIndex}.id", $existingSlot?->id) }}">
                            <input type="hidden" name="slots[{{ $slotIndex }}][slot_type_id]" value="{{ $slotType->id }}">
                            <input type="hidden" name="slots[{{ $slotIndex }}][sort_order]" value="{{ $slotIndex }}">
                        </div>
                        <div class="wb-stack wb-gap-1">
                            <label for="slot_{{ $slotIndex }}_ownership">Ownership</label>
                            <select id="slot_{{ $slotIndex }}_ownership" name="slots[{{ $slotIndex }}][ownership]" class="wb-select">
                                <option value="layout" @selected($ownership === 'layout')>Layout-owned</option>
                                <option value="page" @selected($ownership === 'page')>Page-owned</option>
                            </select>
                        </div>
                        <div class="wb-stack wb-gap-1">
                            <label for="slot_{{ $slotIndex }}_wrapper_preset">Wrapper Preset</label>
                            <select id="slot_{{ $slotIndex }}_wrapper_preset" name="slots[{{ $slotIndex }}][wrapper_preset]" class="wb-select">
                                <option value="default" @selected($wrapperPreset === 'default')>Default</option>
                                <option value="docs-navbar" @selected($wrapperPreset === 'docs-navbar')>Docs Navbar</option>
                                <option value="docs-sidebar" @selected($wrapperPreset === 'docs-sidebar')>Docs Sidebar</option>
                                <option value="docs-main" @selected($wrapperPreset === 'docs-main')>Docs Main</option>
                                <option value="plain" @selected($wrapperPreset === 'plain')>Plain</option>
                            </select>
                        </div>
                        <div class="wb-stack wb-gap-1 wb-justify-end">
                            <label>Blocks</label>
                            @if ($existingSlot?->exists && $isEnabled)
                                <a href="{{ route('admin.layout-types.slots.blocks', [$layoutType, $existingSlot]) }}" class="wb-btn wb-btn-secondary">Edit Blocks</a>
                            @else
                                <span class="wb-text-sm wb-text-muted">Save first</span>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <x-admin.form-actions :cancel-url="route('admin.layout-types.index')" />
</div>
