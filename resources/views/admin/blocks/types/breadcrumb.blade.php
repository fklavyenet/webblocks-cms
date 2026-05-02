@php
    $settings = $block->settings ?? [];
    $homeLabel = old('breadcrumb_home_label', $settings['home_label'] ?? '');
    $includeCurrent = old('breadcrumb_include_current', ($settings['include_current'] ?? true) ? '1' : '0');
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-alert wb-alert-info">
        <div>
            <div class="wb-alert-title">System Breadcrumb</div>
            <div>Breadcrumb is generated from the current page path and translation context. It is appropriate for header and context bars, not full navigation menus.</div>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="breadcrumb_home_label">Home label</label>
            <input id="breadcrumb_home_label" name="breadcrumb_home_label" class="wb-input" type="text" value="{{ $homeLabel }}" placeholder="Home">
            <span class="wb-text-sm wb-text-muted">Optional override for the root item label. Leave blank to use the translated home page name or Home.</span>
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="breadcrumb_include_current">Current page item</label>
            <select id="breadcrumb_include_current" name="breadcrumb_include_current" class="wb-select">
                <option value="1" @selected((string) $includeCurrent === '1')>Include current page</option>
                <option value="0" @selected((string) $includeCurrent === '0')>Hide current page</option>
            </select>
            <span class="wb-text-sm wb-text-muted">When enabled, the final breadcrumb item renders as the current page label.</span>
        </div>
    </div>
</div>
