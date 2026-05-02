@php
    $settings = $block->settings ?? [];
    $allowedIcons = ['home', 'rocket', 'layers', 'palette', 'layout', 'box', 'star', 'grid', 'wrench', 'code', 'terminal'];
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Group title is translated per locale. Icon, open state, and admin label stay shared across locales.</div>
        </div>
    @endif

    <div class="wb-alert wb-alert-info">
        <div>This block renders a WebBlocks UI nav group and accepts Sidebar Nav Item children only.</div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">Group Label</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="sidebar_nav_group_icon">Icon</label>
            <select id="sidebar_nav_group_icon" name="sidebar_nav_group_icon" class="wb-select">
                <option value="">No icon</option>
                @foreach ($allowedIcons as $icon)
                    <option value="{{ $icon }}" @selected(old('sidebar_nav_group_icon', $settings['icon'] ?? '') === $icon)>{{ $icon }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="name">Admin Label</label>
            <input id="name" name="name" class="wb-input" type="text" value="{{ old('name', $settings['layout_name'] ?? '') }}" placeholder="Foundation group">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="sidebar_nav_group_initially_open">Initially Open</label>
            <select id="sidebar_nav_group_initially_open" name="sidebar_nav_group_initially_open" class="wb-select">
                <option value="0" @selected((string) old('sidebar_nav_group_initially_open', ($settings['initially_open'] ?? false) ? '1' : '0') === '0')>Closed</option>
                <option value="1" @selected((string) old('sidebar_nav_group_initially_open', ($settings['initially_open'] ?? false) ? '1' : '0') === '1')>Open</option>
            </select>
        </div>
    </div>
</div>
