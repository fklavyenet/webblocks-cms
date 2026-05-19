@php
    $menuKey = old('navigation_menu_key', $block->navigationMenuKey());
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-alert wb-alert-info">
        <div>
            <div class="wb-alert-title">System Navigation</div>
            <div>Renders navigation items assigned to the selected menu. Editorial content fields are not used for this block.</div>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="navigation_menu_key">Menu</label>
            <select id="navigation_menu_key" name="navigation_menu_key" class="wb-select" required>
                @foreach (\App\Models\NavigationItem::menuOptions() as $option => $label)
                    <option value="{{ $option }}" @selected($menuKey === $option)>{{ $label }}</option>
                @endforeach
            </select>
            <span class="wb-text-sm wb-text-muted">Renders navigation items assigned to the selected menu.</span>
        </div>

        <div class="wb-card wb-card-muted">
            <div class="wb-card-body wb-stack wb-gap-1">
                <strong>System Block</strong>
                <span class="wb-text-sm wb-text-muted">Uses navigation items from the Navigation Items tree editor instead of editorial content fields.</span>
            </div>
        </div>
    </div>
</div>
