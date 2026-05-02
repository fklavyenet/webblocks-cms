@php
    $settings = json_decode((string) $block->getRawOriginal('settings'), true);
    $settings = is_array($settings) ? $settings : [];
    $allowedIcons = ['home', 'rocket', 'layers', 'palette', 'layout', 'box', 'star', 'grid', 'wrench', 'code', 'terminal'];
@endphp

<div class="wb-stack wb-gap-4">
    @if (isset($activeLocale) && $block->supportsTranslations())
        <div class="wb-alert wb-alert-info">
            <div>Sidebar link label is translated per locale. URL, target, icon, and active matching stay shared across locales.</div>
        </div>
    @endif

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="title">Label</label>
            <input id="title" name="title" class="wb-input" type="text" value="{{ old('title', $block->title) }}" required>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="url">URL</label>
            <input id="url" name="url" class="wb-input" type="text" value="{{ old('url', $settings['url'] ?? $block->url) }}" placeholder="/p/about" required>
        </div>
    </div>

    <div class="wb-grid wb-grid-3">
        <div class="wb-stack wb-gap-1">
            <label for="sidebar_nav_item_icon">Icon</label>
            <select id="sidebar_nav_item_icon" name="sidebar_nav_item_icon" class="wb-select">
                <option value="">No icon</option>
                @foreach ($allowedIcons as $icon)
                    <option value="{{ $icon }}" @selected(old('sidebar_nav_item_icon', $settings['icon'] ?? '') === $icon)>{{ $icon }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="sidebar_nav_item_active_mode">Active Matching</label>
            <select id="sidebar_nav_item_active_mode" name="sidebar_nav_item_active_mode" class="wb-select">
                @foreach (['path' => 'Path', 'exact' => 'Exact URL', 'current-page' => 'Current Page', 'manual' => 'Manual'] as $value => $label)
                    <option value="{{ $value }}" @selected(old('sidebar_nav_item_active_mode', $settings['active_mode'] ?? 'path') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="target">Target</label>
            <select id="target" name="target" class="wb-select">
                <option value="_self" @selected(old('target', $settings['target'] ?? '_self') === '_self')>Same tab</option>
                <option value="_blank" @selected(old('target', $settings['target'] ?? '_self') === '_blank')>New tab</option>
            </select>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="sidebar_nav_item_manual_active">Manual Active Fallback</label>
        <select id="sidebar_nav_item_manual_active" name="sidebar_nav_item_manual_active" class="wb-select">
            <option value="0" @selected((string) old('sidebar_nav_item_manual_active', ($settings['manual_active'] ?? false) ? '1' : '0') === '0')>Off</option>
            <option value="1" @selected((string) old('sidebar_nav_item_manual_active', ($settings['manual_active'] ?? false) ? '1' : '0') === '1')>On</option>
        </select>
        <div class="wb-text-sm wb-text-muted">Used only when active matching mode is set to manual.</div>
    </div>
</div>
