@php
    $menuKey = old("{$prefix}.navigation_menu_key", $block->navigationMenuKey());
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-alert wb-alert-info">
        <div>
            <div class="wb-alert-title">System Navigation</div>
            <div>This block renders navigation items for the selected menu.</div>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="block_{{ $index }}_navigation_menu_key">Menu</label>
        <select id="block_{{ $index }}_navigation_menu_key" name="{{ $prefix }}[navigation_menu_key]" class="wb-select" required>
            @foreach (\App\Models\NavigationItem::menuOptions() as $option => $label)
                <option value="{{ $option }}" @selected($menuKey === $option)>{{ $label }}</option>
            @endforeach
        </select>
        <span class="wb-text-sm wb-text-muted">Renders navigation items assigned to the selected menu.</span>
    </div>
</div>
